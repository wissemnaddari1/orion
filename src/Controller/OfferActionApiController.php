<?php

namespace App\Controller;

use App\Entity\Negotiation;
use App\Entity\Offer;
use App\Repository\NegotiationRepository;
use App\Repository\OfferRepository;
use App\Service\ContractFromOfferService;
use App\Service\NotificationService;
use App\Service\OfferMailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * JSON endpoints used by the notification dropdown for
 * Accept / Decline / Negotiate actions.
 *
 * The JS calls:
 *   POST /offers/{id}/accept
 *   POST /offers/{id}/decline
 *   POST /offers/{id}/negotiate  (body: { budget, deadline, message })
 *
 * These routes are intentionally CSRF-free and XHR-only, since they are
 * invoked via `fetch` with `X-Requested-With: XMLHttpRequest`.
 */
#[Route('/offers', name: 'offers_')]
final class OfferActionApiController extends BaseController
{
    public function __construct(
        private OfferRepository $offerRepository,
        private NegotiationRepository $negotiationRepository,
        private EntityManagerInterface $entityManager,
        private OfferMailerService $mailerService,
        private NotificationService $notificationService,
        private ContractFromOfferService $contractFromOfferService,
    ) {
    }

    #[Route('/{id}/accept', name: 'accept', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function accept(Request $request, int $id): JsonResponse
    {
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => false, 'error' => 'XHR only.'], 400);
        }

        $offer = $this->offerRepository->find($id);
        if (!$offer instanceof Offer) {
            return new JsonResponse(['success' => false, 'error' => 'Offer not found.'], 404);
        }

        $user = $this->getUser();
        $isClient = $this->isClientOwner($offer, $user);
        $isWorker = $this->isWorkerOwner($offer, $user);
        if (!$isClient && !$isWorker) {
            return new JsonResponse(['success' => false, 'error' => 'Access denied.'], 403);
        }

        if (!in_array($offer->getStatus(), ['PENDING', 'NEGOTIATING'], true)) {
            return new JsonResponse(['success' => false, 'error' => 'Offer can no longer be accepted.'], 400);
        }

        if ($isClient) {
            // If accepting from negotiation, adopt the negotiated price
            if ($offer->getStatus() === 'NEGOTIATING') {
                $negotiation = $this->negotiationRepository->findLatestByOffer($offer);
                if ($negotiation instanceof Negotiation) {
                    $negotiation->setStatus('ACCEPTED');
                    $negotiation->setLastActionAt(new \DateTime());
                    if ($negotiation->getCounterPrice() !== null) {
                        $offer->setPrice($negotiation->getCounterPrice());
                    }
                }
            }

            $offer->setStatus(Offer::STATUS_ACCEPTED);

            // Reject other competing offers for the same request
            $otherOffers = $this->offerRepository->createQueryBuilder('o')
                ->where('o.serviceRequest = :sr')
                ->andWhere('o.id != :id')
                ->andWhere('o.status IN (:statuses)')
                ->setParameter('sr', $offer->getServiceRequest())
                ->setParameter('id', $offer->getId())
                ->setParameter('statuses', ['PENDING', 'NEGOTIATING'])
                ->getQuery()
                ->getResult();

            foreach ($otherOffers as $other) {
                if ($other instanceof Offer) {
                    $other->setStatus(Offer::STATUS_REJECTED);
                }
            }

            if ($offer->getServiceRequest()) {
                $offer->getServiceRequest()->setStatus('IN_PROGRESS');
            }

            // Create contract from accepted offer
            $this->contractFromOfferService->createFromAcceptedOffer($offer);

            $this->entityManager->flush();

            // Notify freelancer
            $this->mailerService->sendOfferStatusEmail($offer);
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
        } else {
            // Worker-side accept acknowledges the match and notifies the client.
            $client = $offer->getClient();
            if ($client !== null) {
                $workerName = $offer->getWorker()?->getFullName() ?? 'Freelancer';
                $this->notificationService->notifyClientOfferMatch(
                    $client,
                    $offer->getServiceRequest()?->getId() ?? 0,
                    $offer->getId() ?? 0,
                    $workerName,
                    $offer->getMatchScore()
                );
            }
            $this->entityManager->flush();
        }

        return new JsonResponse(['success' => true, 'status' => 'accepted']);
    }

    #[Route('/{id}/decline', name: 'decline', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function decline(Request $request, int $id): JsonResponse
    {
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => false, 'error' => 'XHR only.'], 400);
        }

        $offer = $this->offerRepository->find($id);
        if (!$offer instanceof Offer) {
            return new JsonResponse(['success' => false, 'error' => 'Offer not found.'], 404);
        }

        $user = $this->getUser();
        $isClient = $this->isClientOwner($offer, $user);
        $isWorker = $this->isWorkerOwner($offer, $user);
        if (!$isClient && !$isWorker) {
            return new JsonResponse(['success' => false, 'error' => 'Access denied.'], 403);
        }

        if (!in_array($offer->getStatus(), ['PENDING', 'NEGOTIATING'], true)) {
            return new JsonResponse(['success' => false, 'error' => 'Offer can no longer be rejected.'], 400);
        }

        $offer->setStatus(Offer::STATUS_REJECTED);
        $this->entityManager->flush();

        if ($isClient) {
            // Notify freelancer
            $this->mailerService->sendOfferStatusEmail($offer);
            $freelancer = $offer->getWorker();
            if ($freelancer !== null) {
                $this->notificationService->notifyOfferStatusUpdated(
                    $freelancer,
                    Offer::STATUS_REJECTED,
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
                    Offer::STATUS_REJECTED,
                    $offer->getId(),
                    $offer->getServiceRequest()?->getId() ?? 0,
                    'The freelancer declined this offer.'
                );
                $this->entityManager->flush();
            }
        }

        return new JsonResponse(['success' => true, 'status' => 'rejected']);
    }

    #[Route('/{id}/negotiate', name: 'negotiate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function negotiate(Request $request, int $id): JsonResponse
    {
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => false, 'error' => 'XHR only.'], 400);
        }

        $offer = $this->offerRepository->find($id);
        if (!$offer instanceof Offer) {
            return new JsonResponse(['success' => false, 'error' => 'Offer not found.'], 404);
        }

        if ($offer->getServiceRequest()?->getClient() !== $this->getUser()) {
            return new JsonResponse(['success' => false, 'error' => 'Access denied.'], 403);
        }

        if ($offer->getStatus() !== 'PENDING') {
            return new JsonResponse(['success' => false, 'error' => 'Offer can no longer be negotiated.'], 400);
        }

        // Decode JSON body safely (no exceptions on invalid JSON)
        $rawBody = $request->getContent() ?: '{}';
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $budget = isset($payload['budget']) && $payload['budget'] !== '' ? (float) $payload['budget'] : null;
        $deadline = $payload['deadline'] ?? null; // not used for now
        $message = $payload['message'] ?? '';

        $sr = $offer->getServiceRequest();
        $clientBudgetMid = null;
        if ($budget !== null) {
            $clientBudgetMid = $budget;
        } elseif ($sr && $sr->getBudgetMin() !== null && $sr->getBudgetMax() !== null) {
            $clientBudgetMid = ((float) $sr->getBudgetMin() + (float) $sr->getBudgetMax()) / 2;
        } elseif ($sr && $sr->getBudgetMax() !== null) {
            $clientBudgetMid = (float) $sr->getBudgetMax();
        } elseif ($sr && $sr->getBudgetMin() !== null) {
            $clientBudgetMid = (float) $sr->getBudgetMin();
        }

        if ($clientBudgetMid === null) {
            return new JsonResponse(['success' => false, 'error' => 'Cannot negotiate: no budget information.'], 400);
        }

        $counterPrice = round(($clientBudgetMid + (float) $offer->getPrice()) / 2, 2);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $negotiation = new Negotiation();
        $negotiation->setOffer($offer);
        $negotiation->setOpenedBy($user);
        $negotiation->setTargetUser($offer->getWorker());
        $negotiation->setStatus('OPEN');
        $negotiation->setCounterPrice((string) $counterPrice);
        $negotiation->setSubject('Price negotiation for: ' . ($sr?->getTitle() ?? ''));
        $negotiation->setTimelineDays($offer->getEstimatedTimeDays());
        $negotiation->setScopeDetails($message ?: null);
        $negotiation->setLastActionAt(new \DateTime());

        $offer->setStatus('NEGOTIATING');

        $this->entityManager->persist($negotiation);
        $this->entityManager->flush();

        $this->mailerService->sendOfferStatusEmail($offer);

        return new JsonResponse([
            'success' => true,
            'status' => 'negotiating',
            'counter_price' => $counterPrice,
            'deadline' => $deadline,
        ]);
    }

    private function isClientOwner(Offer $offer, $user): bool
    {
        $client = $offer->getServiceRequest()?->getClient();
        return $client !== null && $user !== null && $client === $user;
    }

    private function isWorkerOwner(Offer $offer, $user): bool
    {
        $worker = $offer->getWorker();
        return $worker !== null && $user !== null && $worker === $user;
    }
}

