<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Contract;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\StripeClient;
use Stripe\Webhook;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class StripeCheckoutService
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $secretKey,
        private readonly string $webhookSecret,
    ) {
    }

    public function isEnabled(): bool
    {
        $key = trim($this->secretKey);

        return $key !== '' && str_starts_with($key, 'sk_');
    }

    public function createUpfrontCheckoutUrl(Contract $contract): string
    {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('Stripe is not configured. Set STRIPE_SECRET_KEY in your environment.');
        }

        $amountCents = $this->moneyToCents($contract->getUpfrontAmount());
        if ($amountCents < 50) {
            throw new \RuntimeException('Upfront amount is too low for Stripe checkout.');
        }

        $currency = strtolower($contract->getCurrency());

        $successBase = $this->urlGenerator->generate(
            'client_contract_show',
            ['id' => $contract->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        $cancelBase = $successBase;

        $successUrl = $successBase . (str_contains($successBase, '?') ? '&' : '?')
            . 'payment=success&session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $cancelBase . (str_contains($cancelBase, '?') ? '&' : '?')
            . 'payment=cancelled';

        $client = new StripeClient($this->secretKey);

        $session = $client->checkout->sessions->create([
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'customer_email' => $contract->getClient()?->getEmail(),
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $amountCents,
                    'product_data' => [
                        'name' => sprintf('Upfront payment for contract #%d', $contract->getId()),
                        'description' => (string) $contract->getTitle(),
                    ],
                ],
            ]],
            'metadata' => [
                'payment_type' => 'upfront',
                'contract_id' => (string) $contract->getId(),
            ],
        ]);

        $url = $session->url;
        if (!is_string($url) || $url === '') {
            throw new \RuntimeException('Stripe Checkout session was created without a redirect URL.');
        }

        return $url;
    }

    public function constructWebhookEvent(string $payload, ?string $signatureHeader): Event
    {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('Stripe is not configured.');
        }
        if ($signatureHeader === null || trim($signatureHeader) === '') {
            throw new \RuntimeException('Missing Stripe-Signature header.');
        }
        if (trim($this->webhookSecret) === '') {
            throw new \RuntimeException('Stripe webhook secret is not configured.');
        }

        /** @var Event $event */
        $event = Webhook::constructEvent($payload, $signatureHeader, $this->webhookSecret);

        return $event;
    }

    private function moneyToCents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
