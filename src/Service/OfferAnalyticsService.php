<?php

namespace App\Service;

use App\Repository\OfferRepository;
use App\Repository\NegotiationRepository;
use App\Repository\ContractRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class OfferAnalyticsService
{
    private string $mlServiceUrl;

    public function __construct(
        private OfferRepository $offerRepository,
        private NegotiationRepository $negotiationRepository,
        private ContractRepository $contractRepository,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        string $mlServiceUrl,
    ) {
        $this->mlServiceUrl = rtrim($mlServiceUrl, '/');
    }

    /**
     * Aggregates marketplace data for the conversion funnel.
     */
    public function getFunnelData(): array
    {
        $totalOffers = $this->offerRepository->count([]);
        $negotiations = $this->negotiationRepository->count([]);
        $accepted = $this->offerRepository->count(['status' => 'ACCEPTED']);
        $converted = $this->contractRepository->count([]);

        // Ensure logical funnel sequence (though data might vary in a dev environment)
        return [
            'total_offers' => $totalOffers,
            'negotiations' => $negotiations,
            'accepted'     => $accepted,
            'converted'    => $converted,
        ];
    }

    /**
     * Aggregates conversion rate and average price using SQL aggregates (no object hydration).
     */
    public function getConversionStats(): array
    {
        $allOffersCount = $this->offerRepository->count([]);

        $result = $this->offerRepository->createQueryBuilder('o')
            ->select('COUNT(o.id) AS accepted_count, AVG(o.price) AS avg_price')
            ->where('o.status = :status')
            ->setParameter('status', 'ACCEPTED')
            ->getQuery()
            ->getSingleResult();

        $acceptedCount = (int) ($result['accepted_count'] ?? 0);
        $avgPrice      = (float) ($result['avg_price'] ?? 0);

        $conversionRate = $allOffersCount > 0 ? round(($acceptedCount / $allOffersCount) * 100, 2) : 0;
        $averagePrice   = round($avgPrice, 2);

        return [
            'conversion_rate' => $conversionRate,
            'average_price'   => $averagePrice,
        ];
    }

    /**
     * Calculates the daily acceptance rate over the past x days.
     */
    public function getAcceptanceTrend(int $days = 14): array
    {
        $startDate = (new \DateTime())->modify("-$days days");
        $startDate->setTime(0, 0, 0);

        // Fetch offers created from $startDate onwards (cap to avoid unbounded load)
        $qb = $this->offerRepository->createQueryBuilder('o')
            ->select('o.createdAt, o.status')
            ->where('o.createdAt >= :startDate')
            ->setParameter('startDate', $startDate)
            ->orderBy('o.createdAt', 'ASC')
            ->setMaxResults(5000);

        $offers = $qb->getQuery()->getArrayResult();

        $dailyData = [];
        // Initialize days to guarantee no missing dates in the chart
        for ($i = $days; $i >= 0; $i--) {
            $dateStr = (new \DateTime())->modify("-$i days")->format('Y-m-d');
            $dailyData[$dateStr] = ['total' => 0, 'accepted' => 0];
        }

        foreach ($offers as $offer) {
            $createdAt = $offer['createdAt'];
            $dateStr = $createdAt instanceof \DateTimeInterface
                ? $createdAt->format('Y-m-d')
                : (new \DateTime($createdAt))->format('Y-m-d');
            if (isset($dailyData[$dateStr])) {
                $dailyData[$dateStr]['total']++;
                if (($offer['status'] ?? '') === 'ACCEPTED') {
                    $dailyData[$dateStr]['accepted']++;
                }
            }
        }

        $trend = [];
        foreach ($dailyData as $date => $data) {
            $trend[] = [
                'date' => $date,
                'acceptance_rate' => $data['total'] > 0 
                    ? round(($data['accepted'] / $data['total']) * 100, 2) 
                    : 0,
            ];
        }

        return $trend;
    }

    /**
     * Fetches the AI score relationship with acceptance rate from the ML Service.
     */
    public function getAiImpactStats(): array
    {
        try {
            $response = $this->httpClient->request('GET', $this->mlServiceUrl . '/analytics/ai-impact', [
                'timeout' => 5,
            ]);

            if ($response->getStatusCode() === 200) {
                return $response->toArray();
            }
            $this->logger->error('ML Analytics Service Error: ' . $response->getStatusCode());
        } catch (\Exception $e) {
            $this->logger->error('ML Analytics Service Exception: ' . $e->getMessage());
        }

        // Fallback or error state
        return [
            ['range' => '0-40', 'rate' => 0],
            ['range' => '40-60', 'rate' => 0],
            ['range' => '60-80', 'rate' => 0],
            ['range' => '80-100', 'rate' => 0]
        ];
    }
}
