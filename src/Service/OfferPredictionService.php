<?php

namespace App\Service;

use App\Entity\Offer;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class OfferPredictionService
{
    private string $mlServiceUrl;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        string $mlServiceUrl,
    ) {
        $this->mlServiceUrl = rtrim($mlServiceUrl, '/');
    }

    public function predict(Offer $offer): ?array
    {
        try {
            $payload = $this->formatPayload($offer);
            if (!$payload) return null;

            $response = $this->httpClient->request('POST', $this->mlServiceUrl . '/predict-offer', [
                'json' => $payload,
                'timeout' => 5,
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('ML Prediction Service Error: ' . $response->getStatusCode());
                return null;
            }

            return $response->toArray();

        } catch (\Exception $e) {
            $this->logger->error('ML Prediction Service Exception: ' . $e->getMessage());
            return null;
        }
    }

    public function predictBatch(array $offers): array
    {
        if (empty($offers)) return [];

        try {
            $requests = [];
            foreach ($offers as $offer) {
                $payload = $this->formatPayload($offer);
                if ($payload) {
                    $requests[] = $payload;
                }
            }

            if (empty($requests)) return [];

            $response = $this->httpClient->request('POST', $this->mlServiceUrl . '/predict-offers', [
                'json' => ['requests' => $requests],
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('ML Batch Prediction Error: ' . $response->getStatusCode());
                return [];
            }

            $results = $response->toArray();
            
            if ($results === null) {
                return [];
            }

            $mappedResults = [];
            $i = 0;
            foreach ($offers as $offer) {
                // Match results to offers that were included in the request
                if ($offer->getWorker() !== null && $offer->getServiceRequest() !== null) {
                    if (isset($results[$i])) {
                        $mappedResults[$offer->getId()] = $results[$i];
                        $i++;
                    }
                }
            }

            return $mappedResults;

        } catch (\Exception $e) {
            $this->logger->error('ML Batch Prediction Exception: ' . $e->getMessage());
            return [];
        }
    }

    private function formatPayload(Offer $offer): ?array
    {
        $serviceRequest = $offer->getServiceRequest();
        $worker = $offer->getWorker();

        if (!$serviceRequest || !$worker) {
            return null;
        }

        return [
            'offer' => [
                'price' => (float) $offer->getPrice(),
                'message' => $offer->getMessage() ?? '',
                'estimated_time_days' => (float) $offer->getEstimatedTimeDays(),
                'included_revisions' => $offer->getIncludedRevisions(),
                'deliverables' => $offer->getDeliverables() ?? '',
            ],
            'service_request' => [
                'budget_min' => (float) $serviceRequest->getBudgetMin(),
                'budget_max' => (float) $serviceRequest->getBudgetMax(),
                'duration' => (float) $serviceRequest->getDuration(),
            ],
            'worker' => [
                'rating_avg' => (float) ($worker->getRatingAvg() ?? 0),
                'total_reviews' => (int) ($worker->getTotalReviews() ?? 0),
            ],
        ];
    }

    public function analyzeEnhancement(array $offerData, array $requestData, array $workerData): ?array
    {
        try {
            $payload = [
                'offer' => [
                    'price' => (float) ($offerData['price'] ?? 0),
                    'message' => $offerData['message'] ?? '',
                    'estimated_time_days' => (float) ($offerData['estimated_time_days'] ?? 0),
                    'included_revisions' => (int) ($offerData['included_revisions'] ?? 0),
                    'deliverables' => $offerData['deliverables'] ?? '',
                ],
                'service_request' => [
                    'budget_min' => (float) ($requestData['budget_min'] ?? 0),
                    'budget_max' => (float) ($requestData['budget_max'] ?? 0),
                    'duration' => (float) ($requestData['duration'] ?? 1),
                ],
                'worker' => [
                    'rating_avg' => (float) ($workerData['rating_avg'] ?? 0),
                    'total_reviews' => (int) ($workerData['total_reviews'] ?? 0),
                ],
            ];

            $response = $this->httpClient->request('POST', $this->mlServiceUrl . '/enhance-offer', [
                'json' => $payload,
                'timeout' => 5,
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('ML Enhancement Service Error: ' . $response->getStatusCode());
                return null;
            }

            return $response->toArray();

        } catch (\Exception $e) {
            $this->logger->error('ML Enhancement Service Exception: ' . $e->getMessage());
            return null;
        }
    }
}
