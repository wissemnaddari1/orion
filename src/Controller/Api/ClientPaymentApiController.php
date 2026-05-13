<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\BaseController;
use App\Entity\Contract;
use App\Entity\User;
use App\Repository\ContractRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/client/payments', name: 'api_client_payments_')]
#[IsGranted('ROLE_CLIENT')]
final class ClientPaymentApiController extends BaseController
{
    /** @var array<string, string> */
    private const MOCK_DECLINED_TEST_CARDS = [
        '4000000000000002' => 'card_declined',
        '4000000000009995' => 'insufficient_funds',
        '4000000000000069' => 'expired_card',
    ];

    /** @var string[] */
    private const MOCK_SUCCESS_TEST_CARDS = [
        '4242424242424242',
        '5555555555554444',
        '378282246310005',
    ];

    public function __construct(
        private readonly ContractRepository $contractRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $paymentProvider = 'stripe',
    ) {
    }

    #[Route('/contracts/{id}/upfront/mock-card', name: 'upfront_mock_card', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function payUpfrontWithMockCard(Request $request, int $id): JsonResponse
    {
        $provider = strtolower(trim($this->paymentProvider));
        if (!in_array($provider, ['mock', 'sandbox', 'test'], true)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'mock_provider_disabled',
                'message' => 'Set PAYMENT_PROVIDER=mock to use mock card API payments.',
            ], Response::HTTP_CONFLICT);
        }

        $contract = $this->contractRepository->find($id);
        if (!$contract instanceof Contract) {
            return new JsonResponse([
                'success' => false,
                'error' => 'not_found',
                'message' => 'Contract not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'success' => false,
                'error' => 'unauthorized',
                'message' => 'Authentication required.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($contract->getClient()?->getId() !== $user->getId()) {
            return new JsonResponse([
                'success' => false,
                'error' => 'not_found',
                'message' => 'Contract not found or access denied.',
            ], Response::HTTP_NOT_FOUND);
        }

        if (!in_array($contract->getStatus(), [Contract::STATUS_ACTIVE, Contract::STATUS_IN_PROGRESS], true)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'invalid_contract_status',
                'message' => 'Upfront funding is only available for active contracts.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!$contract->isFullySigned()) {
            return new JsonResponse([
                'success' => false,
                'error' => 'contract_not_fully_signed',
                'message' => 'Both signatures are required before funding upfront.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($contract->isUpfrontPaid()) {
            return new JsonResponse([
                'success' => true,
                'already_paid' => true,
                'message' => 'Upfront amount is already funded.',
                'contract_id' => $contract->getId(),
            ], Response::HTTP_OK);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'invalid_json',
                'message' => 'Request body must be valid JSON.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $validation = $this->validateCardPayload($payload);
        if (!$validation['valid']) {
            return new JsonResponse([
                'success' => false,
                'error' => 'invalid_card_data',
                'message' => 'Invalid card payload.',
                'details' => $validation['errors'],
            ], Response::HTTP_BAD_REQUEST);
        }

        $cardNumber = (string) $validation['card_number'];
        $simulated = $this->simulateMockPaymentOutcome($cardNumber);
        if (!$simulated['success']) {
            return new JsonResponse([
                'success' => false,
                'error' => (string) $simulated['error'],
                'message' => 'Mock payment failed for this test card.',
                'contract_id' => $contract->getId(),
            ], Response::HTTP_PAYMENT_REQUIRED);
        }

        $contract->markUpfrontPaid();
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Mock upfront payment completed successfully.',
            'contract_id' => $contract->getId(),
            'payment' => [
                'provider' => 'mock',
                'payment_id' => 'mockpay_' . bin2hex(random_bytes(8)),
                'brand' => $this->detectCardBrand($cardNumber),
                'last4' => substr($cardNumber, -4),
                'amount' => $contract->getUpfrontAmount(),
                'currency' => $contract->getCurrency(),
            ],
        ], Response::HTTP_OK);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{valid: bool, errors: array<string,string>, card_number?: string}
     */
    private function validateCardPayload(array $payload): array
    {
        $errors = [];

        $cardNumberRaw = (string) ($payload['card_number'] ?? '');
        $cardNumber = preg_replace('/\D+/', '', $cardNumberRaw) ?? '';
        if ($cardNumber === '') {
            $errors['card_number'] = 'card_number is required.';
        } elseif (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
            $errors['card_number'] = 'card_number length is invalid.';
        } elseif (!$this->passesLuhn($cardNumber)) {
            $errors['card_number'] = 'card_number failed checksum validation.';
        } elseif (
            !in_array($cardNumber, self::MOCK_SUCCESS_TEST_CARDS, true)
            && !array_key_exists($cardNumber, self::MOCK_DECLINED_TEST_CARDS)
        ) {
            $errors['card_number'] = 'Unsupported test card. Use known test card numbers only.';
        }

        $expMonth = (int) ($payload['exp_month'] ?? 0);
        if ($expMonth < 1 || $expMonth > 12) {
            $errors['exp_month'] = 'exp_month must be between 1 and 12.';
        }

        $expYear = $this->normalizeYear((string) ($payload['exp_year'] ?? ''));
        if ($expYear === null) {
            $errors['exp_year'] = 'exp_year must be a 2-digit or 4-digit year.';
        }

        if (!isset($errors['exp_month'], $errors['exp_year']) && $expYear !== null) {
            $now = new \DateTimeImmutable('first day of this month 00:00:00');
            $exp = \DateTimeImmutable::createFromFormat('!Y-n-j', sprintf('%04d-%d-1', $expYear, $expMonth));
            if (!$exp instanceof \DateTimeImmutable || $exp < $now) {
                $errors['expiry'] = 'Card is expired.';
            }
        }

        $cvc = (string) ($payload['cvc'] ?? '');
        if (!preg_match('/^\d{3,4}$/', $cvc)) {
            $errors['cvc'] = 'cvc must be 3 or 4 digits.';
        }

        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }

        return [
            'valid' => true,
            'errors' => [],
            'card_number' => $cardNumber,
        ];
    }

    private function normalizeYear(string $raw): ?int
    {
        $raw = trim($raw);
        if (!preg_match('/^\d{2,4}$/', $raw)) {
            return null;
        }

        if (strlen($raw) === 2) {
            return 2000 + (int) $raw;
        }

        return (int) $raw;
    }

    private function passesLuhn(string $number): bool
    {
        $sum = 0;
        $alt = false;

        for ($i = strlen($number) - 1; $i >= 0; --$i) {
            $n = (int) $number[$i];
            if ($alt) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
            $alt = !$alt;
        }

        return ($sum % 10) === 0;
    }

    /**
     * @return array{success: bool, error?: string}
     */
    private function simulateMockPaymentOutcome(string $cardNumber): array
    {
        if (isset(self::MOCK_DECLINED_TEST_CARDS[$cardNumber])) {
            return [
                'success' => false,
                'error' => self::MOCK_DECLINED_TEST_CARDS[$cardNumber],
            ];
        }

        return ['success' => true];
    }

    private function detectCardBrand(string $cardNumber): string
    {
        if (str_starts_with($cardNumber, '4')) {
            return 'visa';
        }
        if (str_starts_with($cardNumber, '5')) {
            return 'mastercard';
        }
        if (str_starts_with($cardNumber, '34') || str_starts_with($cardNumber, '37')) {
            return 'amex';
        }

        return 'unknown';
    }
}

