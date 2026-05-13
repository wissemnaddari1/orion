<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Offer;
use App\Entity\ServiceRequest;
use Doctrine\ORM\EntityManagerInterface;

/**
 * After a Service Request is created, runs matchmaking and creates PENDING offers
 * plus notifications for top freelancers and the client.
 */
final class ServiceRequestMatchmakingService
{
    public function __construct(
        private MatchmakingRecommendationProviderInterface $aiMatchmakingService,
        private NotificationService $notificationService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function runMatchmaking(ServiceRequest $serviceRequest): void
    {
        $recommendations = $this->aiMatchmakingService->getRecommendationsForServiceRequest(
            $serviceRequest,
            $serviceRequest->getClient()?->getId(),
            ['stage' => 'service_discovery']
        );
        if ($recommendations === []) {
            return;
        }

        $client = $serviceRequest->getClient();
        $budgetAvg = $this->averageBudget($serviceRequest->getBudgetMin(), $serviceRequest->getBudgetMax());
        $duration = $serviceRequest->getDuration() ?: 7;

        $offersToNotify = [];
        foreach ($recommendations as $item) {
            $freelancer = $item['user'];
            $score = (float) $item['score'];

            $existingOffer = $this->entityManager->getRepository(Offer::class)->findOneBy([
                'serviceRequest' => $serviceRequest,
                'worker' => $freelancer,
            ]);
            if ($existingOffer instanceof Offer) {
                continue;
            }

            $offer = new Offer();
            $offer->setServiceRequest($serviceRequest);
            $offer->setWorker($freelancer);
            $offer->setClient($client);
            $offer->setPrice($budgetAvg);
            $offer->setEstimatedTimeDays($duration);
            $offer->setStatus(Offer::STATUS_PENDING);
            $offer->setPriorityLevel('MEDIUM');
            $offer->setMatchScore($score);
            $this->entityManager->persist($offer);
            $offersToNotify[] = [$offer, $freelancer, $score];
        }

        $this->entityManager->flush();

        foreach ($offersToNotify as [$offer, $freelancer, $score]) {
            $this->notificationService->notifyMatchFoundForFreelancer(
                $freelancer,
                $serviceRequest->getId(),
                $offer->getId(),
                $client?->getId() ?? 0,
                $score
            );
        }
        // Client notifications are created only when a freelancer accepts (first 10 per request).

        $this->entityManager->flush();
    }

    private function averageBudget(?string $min, ?string $max): string
    {
        $a = (float) ($min ?? 0);
        $b = (float) ($max ?? 0);
        if ($a <= 0 && $b <= 0) {
            return '0.00';
        }
        if ($a <= 0) {
            return number_format($b, 2, '.', '');
        }
        if ($b <= 0) {
            return number_format($a, 2, '.', '');
        }
        return number_format(($a + $b) / 2, 2, '.', '');
    }
}
