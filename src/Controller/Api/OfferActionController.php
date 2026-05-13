<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Offer;
use App\Repository\NotificationRepository;
use App\Repository\OfferRepository;
use App\Service\ContractFromOfferService;
use App\Service\NotificationService;
use App\Controller\BaseController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/offers', name: 'api_offers_')]
#[IsGranted('ROLE_USER')]
class OfferActionController extends BaseController
{
    private const MAX_CLIENT_OFFER_NOTIFICATIONS_PER_REQUEST = 10;

    public function __construct(
        private OfferRepository $offerRepository,
        private NotificationRepository $notificationRepository,
        private EntityManagerInterface $entityManager,
        private NotificationService $notificationService,
        private ContractFromOfferService $contractFromOfferService,
    ) {
    }

    #[Route('/{id}/accept', name: 'accept', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function accept(int $id): JsonResponse
    {
        $user = $this->getUser();
        $offer = $this->offerRepository->find($id);
        if ($offer === null) {
            return new JsonResponse(['error' => 'Offer not found or access denied'], Response::HTTP_NOT_FOUND);
        }
        $isClient = $this->isClientOwner($offer, $user);
        $isWorker = $this->isWorkerOwner($offer, $user);
        if ($isClient) {
            return $this->acceptAsClient($offer);
        }
        if ($isWorker) {
            return $this->acceptAsFreelancer($offer);
        }
        return new JsonResponse(['error' => 'Offer not found or access denied'], Response::HTTP_NOT_FOUND);
    }

    private function acceptAsClient(Offer $offer): JsonResponse
    {
        if ($offer->getStatus() !== Offer::STATUS_PENDING) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Offer can no longer be accepted',
                'status' => $offer->getStatus(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $offer->setStatus(Offer::STATUS_ACCEPTED);
        $this->rejectOtherPendingOffers($offer);
        $offer->getServiceRequest()?->setStatus('IN_PROGRESS');
        $this->contractFromOfferService->createFromAcceptedOffer($offer);
        $this->entityManager->flush();

        $freelancer = $offer->getWorker();
        if ($freelancer !== null) {
            $this->notificationService->notifyOfferStatusUpdated(
                $freelancer,
                Offer::STATUS_ACCEPTED,
                $offer->getId(),
                $offer->getServiceRequest()?->getId() ?? 0,
                'Your offer has been accepted.'
            );
            $this->entityManager->flush();
        }

        return new JsonResponse([
            'success' => true,
            'offer_id' => $offer->getId(),
            'status' => $offer->getStatus(),
        ]);
    }

    /** Freelancer accepts the match (stays PENDING so client can still accept/decline/negotiate). Notify client, cap at 10 per request. */
    private function acceptAsFreelancer(Offer $offer): JsonResponse
    {
        if ($offer->getStatus() !== Offer::STATUS_PENDING && $offer->getStatus() !== Offer::STATUS_NEGOTIATING) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Offer can no longer be accepted',
                'status' => $offer->getStatus(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $client = $offer->getClient();
        $requestId = $offer->getServiceRequest()?->getId();
        if ($client !== null && $requestId !== null) {
            $currentCount = $this->notificationRepository->countClientOfferNotificationsForRequest($client, $requestId);
            if ($currentCount < self::MAX_CLIENT_OFFER_NOTIFICATIONS_PER_REQUEST) {
                $worker = $offer->getWorker();
                $this->notificationService->notifyClientOfferMatch(
                    $client,
                    $requestId,
                    $offer->getId(),
                    $worker !== null ? $worker->getFullName() : 'Freelancer',
                    $offer->getMatchScore()
                );
            }
        }

        $worker = $offer->getWorker();
        if ($worker !== null) {
            $this->notificationRepository->deleteByUserAndOfferId($worker, $offer->getId());
        }

        $this->entityManager->flush();
        return new JsonResponse([
            'success' => true,
            'offer_id' => $offer->getId(),
            'status' => $offer->getStatus(),
        ]);
    }

    #[Route('/{id}/decline', name: 'decline', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function decline(int $id): JsonResponse
    {
        $user = $this->getUser();
        $offer = $this->offerRepository->find($id);
        if ($offer === null) {
            return new JsonResponse(['error' => 'Offer not found or access denied'], Response::HTTP_NOT_FOUND);
        }
        $isClient = $this->isClientOwner($offer, $user);
        $isWorker = $this->isWorkerOwner($offer, $user);
        if (!$isClient && !$isWorker) {
            return new JsonResponse(['error' => 'Offer not found or access denied'], Response::HTTP_NOT_FOUND);
        }

        if ($offer->getStatus() !== Offer::STATUS_PENDING && $offer->getStatus() !== Offer::STATUS_NEGOTIATING) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Offer can no longer be declined',
                'status' => $offer->getStatus(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $offer->setStatus(Offer::STATUS_DECLINED);
        $this->entityManager->flush();

        if ($isClient) {
            $freelancer = $offer->getWorker();
            if ($freelancer !== null) {
                $this->notificationService->notifyOfferStatusUpdated(
                    $freelancer,
                    Offer::STATUS_DECLINED,
                    $offer->getId(),
                    $offer->getServiceRequest()?->getId() ?? 0,
                    'Your offer was declined.'
                );
                $this->entityManager->flush();
            }
        } else {
            $client = $offer->getClient();
            if ($client !== null) {
                $this->notificationService->notifyOfferStatusUpdated(
                    $client,
                    Offer::STATUS_DECLINED,
                    $offer->getId(),
                    $offer->getServiceRequest()?->getId() ?? 0,
                    'The freelancer declined this offer.'
                );
                $this->entityManager->flush();
            }
        }

        return new JsonResponse([
            'success' => true,
            'offer_id' => $offer->getId(),
            'status' => $offer->getStatus(),
        ]);
    }

    #[Route('/{id}/negotiate', name: 'negotiate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function negotiate(Request $request, int $id): JsonResponse
    {
        $user = $this->getUser();
        $offer = $this->offerRepository->find($id);
        if ($offer === null || !$this->isClientOwner($offer, $user)) {
            return new JsonResponse(['error' => 'Offer not found or access denied'], Response::HTTP_NOT_FOUND);
        }

        if ($offer->getStatus() !== Offer::STATUS_PENDING && $offer->getStatus() !== Offer::STATUS_NEGOTIATING) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Offer can no longer be negotiated',
                'status' => $offer->getStatus(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        if (isset($payload['budget']) && $payload['budget'] !== null && $payload['budget'] !== '') {
            $offer->setProposedBudget((string) $payload['budget']);
        }
        if (isset($payload['deadline']) && $payload['deadline'] !== null && $payload['deadline'] !== '') {
            try {
                $offer->setProposedDeadline(new \DateTime($payload['deadline']));
            } catch (\Exception $e) {
                // ignore invalid date
            }
        }
        if (isset($payload['message']) && $payload['message'] !== null) {
            $offer->setMessage($payload['message']);
        }

        $offer->setStatus(Offer::STATUS_NEGOTIATING);
        $this->entityManager->flush();

        $freelancer = $offer->getWorker();
        if ($freelancer !== null) {
            $this->notificationService->notifyOfferStatusUpdated(
                $freelancer,
                Offer::STATUS_NEGOTIATING,
                $offer->getId(),
                $offer->getServiceRequest()?->getId() ?? 0,
                'The client wants to negotiate. Check the proposed terms.'
            );
            $this->entityManager->flush();
        }

        return new JsonResponse([
            'success' => true,
            'offer_id' => $offer->getId(),
            'status' => $offer->getStatus(),
            'proposed_budget' => $offer->getProposedBudget(),
            'proposed_deadline' => $offer->getProposedDeadline()?->format('Y-m-d'),
        ]);
    }

    private function isClientOwner(Offer $offer, $user): bool
    {
        $client = $offer->getClient();
        return $client !== null && $client->getId() === $user->getId();
    }

    private function isWorkerOwner(Offer $offer, $user): bool
    {
        $worker = $offer->getWorker();
        return $worker !== null && $worker->getId() === $user->getId();
    }

    /** When client accepts one offer, all other pending/negotiating offers for the same request are declined. */
    private function rejectOtherPendingOffers(Offer $acceptedOffer): void
    {
        $sr = $acceptedOffer->getServiceRequest();
        if ($sr === null) {
            return;
        }
        $others = $this->offerRepository->createQueryBuilder('o')
            ->where('o.serviceRequest = :sr')
            ->andWhere('o.id != :id')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('sr', $sr)
            ->setParameter('id', $acceptedOffer->getId())
            ->setParameter('statuses', [Offer::STATUS_PENDING, Offer::STATUS_NEGOTIATING])
            ->getQuery()
            ->getResult();
        foreach ($others as $o) {
            $o->setStatus(Offer::STATUS_REJECTED);
        }
    }
}
