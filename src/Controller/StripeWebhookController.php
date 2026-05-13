<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Contract;
use App\Service\StripeCheckoutService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Exception\SignatureVerificationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/stripe', name: 'stripe_')]
final class StripeWebhookController extends BaseController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StripeCheckoutService $stripeCheckoutService,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    #[Route('/webhook', name: 'webhook', methods: ['POST'])]
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->headers->get('Stripe-Signature');
        if ($signature === null || trim($signature) === '') {
            return new JsonResponse(['error' => 'Missing Stripe-Signature header'], 400);
        }

        try {
            $event = $this->stripeCheckoutService->constructWebhookEvent($payload, $signature);
        } catch (SignatureVerificationException $e) {
            return new JsonResponse(['error' => 'Invalid signature'], 400);
        } catch (\Throwable $e) {
            $this->logger?->error('Stripe webhook setup/config error', ['exception' => $e]);

            return new JsonResponse(['error' => 'Webhook configuration error'], 500);
        }

        if (in_array($event->type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            /** @var object{metadata?: array<string, mixed>, payment_status?: string} $session */
            $session = $event->data->object;
            $metadata = (array) ($session->metadata ?? []);
            $paymentType = (string) ($metadata['payment_type'] ?? '');
            $contractId = (int) ($metadata['contract_id'] ?? 0);
            $paymentStatus = (string) ($session->payment_status ?? '');

            if ($paymentType === 'upfront' && $contractId > 0 && $paymentStatus === 'paid') {
                $contract = $this->entityManager->getRepository(Contract::class)->find($contractId);
                if ($contract instanceof Contract && !$contract->isUpfrontPaid()) {
                    $contract->markUpfrontPaid();
                    $this->entityManager->flush();
                }
            }
        }

        return new JsonResponse(['received' => true]);
    }
}
