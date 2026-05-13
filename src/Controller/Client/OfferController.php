<?php

namespace App\Controller\Client;

use App\Entity\Negotiation;
use App\Entity\Offer;
use App\Repository\NegotiationRepository;
use App\Repository\OfferRepository;
use App\Repository\ServiceRequestRepository;
use App\Service\OfferMailerService;
use App\Service\OfferPredictionService;
use App\Service\ClientSidebarService;
use App\Service\ContractFromOfferService;
use App\Service\NotificationService;
use App\Service\AiMatchmakingService;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/client/offers', name: 'client_offers_')]
#[IsGranted('ROLE_CLIENT')]
class OfferController extends BaseController
{
    public function __construct(
        private OfferRepository $offerRepository,
        private ServiceRequestRepository $serviceRequestRepository,
        private NegotiationRepository $negotiationRepository,
        private EntityManagerInterface $entityManager,
        private OfferMailerService $mailerService,
        private OfferPredictionService $predictionService,
        private \App\Repository\WorkerProfileRepository $workerProfileRepository,
        private ClientSidebarService $clientSidebar,
        private NotificationService $notificationService,
        private ContractFromOfferService $contractFromOfferService,
        private AiMatchmakingService $aiMatchmakingService,
    ) {
    }

    /* ----------- LIST ----------- */

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        $statusFilter = $request->query->get('status', '');
        $searchQuery = $request->query->get('q', '');
        $sortParam = $request->query->get('sort', 'newest');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;

        // Efficiently fetch offers with eager loading
        $result = $this->offerRepository->findForClientIndex(
            $user, 
            $searchQuery, 
            $statusFilter, 
            $sortParam, 
            $page, 
            $limit
        );
        $offers = $result['offers'];
        $total = $result['total'];
        $totalPages = max(1, (int) ceil($total / $limit));

        // Batch-fetch summary statistics and status counts in 2 efficient queries
        $stats = $this->offerRepository->getOffersStatsForClient($user);
        $avgPrice = $stats['avgPrice'];
        $fastestDelivery = $stats['fastestDelivery'];
        $activeBreakdown = $stats['activeBreakdown'];
        $activeTotal = $stats['activeTotal'];

        // Batch-fetch AI predictions (1 HTTP call for the whole page)
        $predictions = $this->predictionService->predictBatch($offers);
        
        $recommendedOfferId = null;
        if (!empty($predictions)) {
            $highestProb = -1;
            foreach ($predictions as $id => $pred) {
                if ($pred && isset($pred['probability']) && $pred['probability'] > $highestProb) {
                    $highestProb = $pred['probability'];
                    $recommendedOfferId = $id;
                }
            }
        }

        // Batch-fetch offer counts for the comparison feature
        $srIds = array_map(fn($o) => $o->getServiceRequest()->getId(), $offers);
        $offersByRequestCount = $this->offerRepository->countByServiceRequestIds(array_unique($srIds));

        $template = $request->isXmlHttpRequest() 
            ? 'pages/client/_offers_list_grid.html.twig' 
            : 'pages/client/offers_list.html.twig';

        return $this->render($template, [
            'offers' => $offers,
            'predictions' => $predictions,
            'offers_by_request_count' => $offersByRequestCount,
            'active_total' => $activeTotal,
            'active_breakdown' => $activeBreakdown,
            'status_filter' => $statusFilter,
            'search_query' => $searchQuery,
            'sort' => $sortParam,
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
            'avg_price' => $avgPrice,
            'fastest_delivery' => $fastestDelivery,
            'recommended_offer_id' => $recommendedOfferId,
            'topbar_title' => 'Received Offers',
            'sidebar_items' => $this->getClientSidebarItems('offers'),
        ]);
    }


    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Offer $offer): Response
    {
        // Ensure the offer belongs to a service request owned by this client
        $this->denyUnlessOwner($offer);

        // Fetch latest negotiation for this offer
        $negotiation = $this->negotiationRepository->findLatestByOffer($offer);

        // Calculate the average price for "negotiate" preview
        $sr = $offer->getServiceRequest();
        $clientBudgetMid = null;
        if ($sr->getBudgetMin() !== null && $sr->getBudgetMax() !== null) {
            $clientBudgetMid = ((float) $sr->getBudgetMin() + (float) $sr->getBudgetMax()) / 2;
        } elseif ($sr->getBudgetMax() !== null) {
            $clientBudgetMid = (float) $sr->getBudgetMax();
        } elseif ($sr->getBudgetMin() !== null) {
            $clientBudgetMid = (float) $sr->getBudgetMin();
        }

        $suggestedPrice = null;
        if ($clientBudgetMid !== null) {
            $suggestedPrice = round(($clientBudgetMid + (float) $offer->getPrice()) / 2, 2);
        }

        // Get AI Prediction
        $prediction = $this->predictionService->predict($offer);
        $matchmakingHint = null;
        $recommendations = $this->aiMatchmakingService->getRecommendationsForServiceRequest(
            $sr,
            $this->getUser()?->getId(),
            ['stage' => 'pre_negotiation']
        );
        foreach ($recommendations as $rec) {
            if ($rec['user']->getId() === $offer->getWorker()?->getId()) {
                $matchmakingHint = $rec;
                break;
            }
        }

        return $this->render('pages/client/offer_show.html.twig', [
            'offer' => $offer,
            'negotiation' => $negotiation,
            'suggested_price' => $suggestedPrice,
            'client_budget_mid' => $clientBudgetMid,
            'prediction' => $prediction,
            'matchmaking_hint' => $matchmakingHint,
            'topbar_title' => 'Offer Details',
            'sidebar_items' => $this->getClientSidebarItems('offers'),
        ]);
    }

    /* ──────────────── COMPARISON ──────────────── */

    #[Route('/compare/{id}', name: 'comparison', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function comparison(int $id, Request $request): Response
    {
        $serviceRequest = $this->serviceRequestRepository->find($id);

        if (!$serviceRequest || $serviceRequest->getClient() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Service request not found or access denied.');
        }

        $offers = $serviceRequest->getOffers();
        
        // Batch-fetch AI predictions (1 call)
        $predictions = $this->predictionService->predictBatch($offers->toArray());

        // Batch-fetch Worker Profiles (1 call instead of N)
        $workerIds = array_map(fn($o) => $o->getWorker()->getId(), $offers->toArray());
        $profiles = $this->workerProfileRepository->createQueryBuilder('wp')
            ->andWhere('wp.user IN (:workers)')
            ->setParameter('workers', array_unique($workerIds))
            ->getQuery()
            ->getResult();
        
        $workerProfiles = [];
        foreach ($profiles as $profile) {
            $workerProfiles[$profile->getUserId()] = $profile;
        }

    return $this->render('pages/client/offers_comparison.html.twig', [
        'request' => $serviceRequest,
        'offers' => $offers,
        'predictions' => $predictions,
        'worker_profiles' => $workerProfiles,
        'topbar_title' => 'Offer Comparison',
        'sidebar_items' => $this->getClientSidebarItems('offers'),
    ]);
    }

    /* ──────────────── ACCEPT ──────────────── */
    /* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ ACCEPT â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */

    #[Route('/{id}/accept', name: 'accept', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function accept(Request $request, Offer $offer): Response
    {
        $this->denyUnlessOwner($offer);

        if (!in_array($offer->getStatus(), ['PENDING', 'NEGOTIATING'])) {
            $this->addFlash('warning', 'This offer can no longer be accepted.');
            return $this->redirectToRoute('client_offers_show', ['id' => $offer->getId()]);
        }

        if (!$this->isCsrfTokenValid('accept' . $offer->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('client_offers_show', ['id' => $offer->getId()]);
        }

        // If accepting from negotiation, use the latest negotiation counter price
        $negotiation = $this->negotiationRepository->findByOffer($offer->getId());
        if ($negotiation && $offer->getStatus() === 'NEGOTIATING') {
            $negotiation->setStatus('ACCEPTED');
            $negotiation->setLastActionAt(new \DateTime());
            // Update offer price to the negotiated price
            $offer->setPrice($negotiation->getCounterPrice());
        }

        // Accept this offer
        $offer->setStatus('ACCEPTED');

        // Reject all other pending/negotiating offers for the same service request
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
            $other->setStatus(Offer::STATUS_REJECTED);
        }

        // Update service request status
        $offer->getServiceRequest()->setStatus('IN_PROGRESS');

        $this->contractFromOfferService->createFromAcceptedOffer($offer);

        $this->entityManager->flush();

        // Send email notification to worker
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

        $this->addFlash('success', 'Offer accepted! The service request is now in progress.');
        return $this->redirectToRoute('client_offers_show', ['id' => $offer->getId()]);
    }

    /* ──────────────── NEGOTIATE ──────────────── */

    #[Route('/{id}/negotiate', name: 'negotiate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function negotiate(Request $request, Offer $offer): Response
    {
        $this->denyUnlessOwner($offer);

        if ($offer->getStatus() !== 'PENDING') {
            $this->addFlash('warning', 'This offer can no longer be negotiated.');
            return $this->redirectToRoute('client_offers_show', ['id' => $offer->getId()]);
        }

        if (!$this->isCsrfTokenValid('negotiate' . $offer->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('client_offers_show', ['id' => $offer->getId()]);
        }

        $message = $request->request->get('message', '');

        // Auto-calculate counter price as average of client budget midpoint and worker price
        $sr = $offer->getServiceRequest();
        $clientBudgetMid = null;
        if ($sr->getBudgetMin() !== null && $sr->getBudgetMax() !== null) {
            $clientBudgetMid = ((float) $sr->getBudgetMin() + (float) $sr->getBudgetMax()) / 2;
        } elseif ($sr->getBudgetMax() !== null) {
            $clientBudgetMid = (float) $sr->getBudgetMax();
        } elseif ($sr->getBudgetMin() !== null) {
            $clientBudgetMid = (float) $sr->getBudgetMin();
        }

        if ($clientBudgetMid === null) {
            $this->addFlash('error', 'Cannot negotiate: no budget set on the service request.');
            return $this->redirectToRoute('client_offers_show', ['id' => $offer->getId()]);
        }

        $counterPrice = round(($clientBudgetMid + (float) $offer->getPrice()) / 2, 2);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Create a new negotiation record
        $negotiation = new Negotiation();
        $negotiation->setOffer($offer);
        $negotiation->setOpenedBy($user);
        $negotiation->setTargetUser($offer->getWorker());
        $negotiation->setStatus('OPEN');
        $negotiation->setCounterPrice((string) $counterPrice);
        $negotiation->setSubject('Price negotiation for: ' . $offer->getServiceRequest()->getTitle());
        $negotiation->setTimelineDays($offer->getEstimatedTimeDays());
        $negotiation->setScopeDetails($message ?: null);
        $negotiation->setLastActionAt(new \DateTime());

        // Update the offer status to NEGOTIATING
        $offer->setStatus('NEGOTIATING');

        $this->entityManager->persist($negotiation);
        $this->entityManager->flush();

        // Send email notification to worker
        $this->mailerService->sendOfferStatusEmail($offer);

        $this->addFlash('success', 'Negotiation started! The average price of $' . number_format((float)$counterPrice, 2) . ' has been proposed to the freelancer.');
        return $this->redirectToRoute('client_offers_show', ['id' => $offer->getId()]);
    }

    /* ──────────────── ABORT NEGOTIATION ──────────────── */

    #[Route('/{id}/abort-negotiation', name: 'abort_negotiation', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function abortNegotiation(Request $request, Offer $offer): Response
    {
        $this->denyUnlessOwner($offer);

        if ($offer->getStatus() !== 'NEGOTIATING') {
            $this->addFlash('warning', 'This offer is not currently under negotiation.');
            return $this->redirectToRoute('client_offers_show', ['id' => $offer->getId()]);
        }

        if (!$this->isCsrfTokenValid('abort_negotiation' . $offer->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('client_offers_show', ['id' => $offer->getId()]);
        }

        // Remove the negotiation
        $negotiation = $offer->getNegotiation();
        if ($negotiation) {
            $this->entityManager->remove($negotiation);
        }

        // Revert offer status back to PENDING
        $offer->setStatus('PENDING');
        $this->entityManager->flush();

        $this->addFlash('success', 'Negotiation has been aborted. The offer is back to pending.');
        return $this->redirectToRoute('client_offers_show', ['id' => $offer->getId()]);
    }

    /* ──────────────── REJECT ──────────────── */
    /* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ REJECT â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */

    #[Route('/{id}/reject', name: 'reject', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reject(Request $request, Offer $offer): Response
    {
        $this->denyUnlessOwner($offer);

        if (!in_array($offer->getStatus(), ['PENDING', 'NEGOTIATING'])) {
            $this->addFlash('warning', 'This offer can no longer be rejected.');
            return $this->redirectToRoute('client_offers_show', ['id' => $offer->getId()]);
        }

        $offer->setStatus(Offer::STATUS_REJECTED);
        $this->entityManager->flush();

        // Send email notification to worker
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

        $this->addFlash('success', 'Offer has been rejected.');
        return $this->redirectToRoute('client_offers_index');
    }

    /* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ HELPERS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */

    private function denyUnlessOwner(Offer $offer): void
    {
        if ($offer->getServiceRequest()->getClient() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You do not own this service request.');
        }
    }

    private function getClientSidebarItems(string $active): array
    {
        return [
            [
                'label' => 'Dashboard',
                'url' => $this->generateUrl('client_dashboard'),
                'active' => $active === 'dashboard',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>',
            ],
            [
                'label' => 'Services',
                'url' => $this->generateUrl('request_list'),
                'active' => $active === 'services',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
            ],
            [
                'label' => 'Offers',
                'url' => $this->generateUrl('client_offers_index'),
                'active' => $active === 'offers',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>',
            ],
            [
                'label' => 'Contracts',
                'url' => $this->generateUrl('client_contracts_list'),
                'active' => $active === 'contracts',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
            ],
            [
                'label' => 'Categories',
                'url' => $this->generateUrl('client_categories_index'),
                'active' => $active === 'categories',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>',
            ],
        ];
    }
}
