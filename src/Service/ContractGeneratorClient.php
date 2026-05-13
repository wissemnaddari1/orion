<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Offer;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ContractGeneratorClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
    ) {
    }

    /**
     * @return array{generatedContract: string, riskScore: float, riskLevel: string}|null
     */
    public function generateFromOffer(Offer $offer): ?array
    {
        $serviceRequest = $offer->getServiceRequest();
        $client = $offer->getClient() ?? $serviceRequest?->getClient();
        $worker = $offer->getWorker();

        if ($serviceRequest === null || $client === null || $worker === null) {
            return null;
        }

        $requirements = $this->buildRequirements($offer, $serviceRequest->getDescription() ?? '');

        $payload = [
            'serviceTitle' => (string) ($serviceRequest->getTitle() ?? 'Untitled Service'),
            'serviceDescription' => (string) ($serviceRequest->getDescription() ?? ''),
            'requirements' => $requirements,
            'price' => (float) $offer->getPrice(),
            'deliveryDays' => max(1, (int) ($offer->getEstimatedTimeDays() > 0 ? $offer->getEstimatedTimeDays() : ($serviceRequest->getDuration() ?? 30))),
            'deliveryMode' => 'ONLINE',
            'clientRating' => (float) ($client->getRatingAvg() ?? 0.0),
            'freelancerRating' => (float) ($worker->getRatingAvg() ?? 0.0),
            'negotiationCount' => $this->inferNegotiationCount($offer),
            'numberOfMilestones' => $this->inferMilestoneCount($offer),
        ];

        try {
            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/generate-contract', [
                'json' => $payload,
                'timeout' => 10,
            ]);
            $data = $response->toArray();
        } catch (ExceptionInterface $e) {
            return null;
        }

        if (!isset($data['generatedContract'], $data['riskScore'], $data['riskLevel'])) {
            return null;
        }

        return [
            'generatedContract' => (string) $data['generatedContract'],
            'riskScore' => (float) $data['riskScore'],
            'riskLevel' => (string) $data['riskLevel'],
        ];
    }

    /**
     * Standalone risk assessment from raw contract parameters (no Offer needed).
     *
     * @param array{title?: string, scope?: string, price?: float, deliveryDays?: int, clientRating?: float, workerRating?: float, milestones?: int} $params
     * @return array{riskScore: float, riskLevel: string}|null
     */
    public function assessRisk(array $params): ?array
    {
        $payload = [
            'serviceTitle'       => (string) ($params['title'] ?? 'Untitled'),
            'serviceDescription' => (string) ($params['scope'] ?? 'No description'),
            'requirements'       => (string) ($params['scope'] ?? 'Standard requirements'),
            'price'              => (float) ($params['price'] ?? 0),
            'deliveryDays'       => max(1, (int) ($params['deliveryDays'] ?? 30)),
            'deliveryMode'       => 'ONLINE',
            'clientRating'       => (float) ($params['clientRating'] ?? 0.0),
            'freelancerRating'   => (float) ($params['workerRating'] ?? 0.0),
            'negotiationCount'   => 0,
            'numberOfMilestones' => max(1, (int) ($params['milestones'] ?? 1)),
        ];

        try {
            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/generate-contract', [
                'json' => $payload,
                'timeout' => 10,
            ]);
            $data = $response->toArray();
        } catch (ExceptionInterface $e) {
            return null;
        }

        if (!isset($data['riskScore'], $data['riskLevel'])) {
            return null;
        }

        return [
            'riskScore' => (float) $data['riskScore'],
            'riskLevel' => (string) $data['riskLevel'],
        ];
    }

    private function buildRequirements(Offer $offer, string $fallbackDescription): string
    {
        $parts = [];

        $scopeSummary = trim((string) ($offer->getScopeSummary() ?? ''));
        if ($scopeSummary !== '') {
            $parts[] = $scopeSummary;
        }

        $deliverables = trim((string) ($offer->getDeliverables() ?? ''));
        if ($deliverables !== '') {
            $parts[] = 'Deliverables: ' . $deliverables;
        }

        $acceptance = trim((string) ($offer->getAcceptanceCriteria() ?? ''));
        if ($acceptance !== '') {
            $parts[] = 'Acceptance criteria: ' . $acceptance;
        }

        $message = trim((string) ($offer->getMessage() ?? ''));
        if ($message !== '') {
            $parts[] = 'Freelancer message: ' . $message;
        }

        if ($parts === []) {
            $fallback = trim($fallbackDescription);
            if ($fallback !== '') {
                return $fallback;
            }

            return 'Standard project requirements apply and must be confirmed in writing.';
        }

        return implode("\n\n", $parts);
    }

    private function inferNegotiationCount(Offer $offer): int
    {
        return $offer->getStatus() === Offer::STATUS_NEGOTIATING ? 1 : 0;
    }

    private function inferMilestoneCount(Offer $offer): int
    {
        $deliverables = trim((string) ($offer->getDeliverables() ?? ''));
        if ($deliverables === '') {
            return 1;
        }

        $parts = preg_split('/\r\n|\r|\n|;|\|/', $deliverables) ?: [];
        $nonEmpty = array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $parts), static fn ($v) => $v !== ''));
        $count = count($nonEmpty);

        return max(1, min(6, $count));
    }
}
