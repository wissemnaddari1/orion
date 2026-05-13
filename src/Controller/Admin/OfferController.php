<?php

namespace App\Controller\Admin;

use App\Entity\Offer;
use App\Entity\ServiceRequest;
use App\Form\OfferType;
use App\Repository\OfferRepository;
use App\Repository\ServiceRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\OfferAnalyticsService;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/offers', name: 'admin_offers_')]
#[IsGranted('ROLE_ADMIN')]
class OfferController extends BaseController
{
    public function __construct(
        private OfferRepository $offerRepository,
        private ServiceRequestRepository $serviceRequestRepository,
        private EntityManagerInterface $entityManager,
        private OfferAnalyticsService $analyticsService,
    ) {
    }

    /* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ LIST â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    /* ──────────────── LIST ──────────────── */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query->get('search', ''));
        $statusFilter = $request->query->get('status', '');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;

        $qb = $this->offerRepository->createQueryBuilder('o')
            ->leftJoin('o.serviceRequest', 'sr')
            ->leftJoin('o.worker', 'w')
            ->orderBy('o.createdAt', 'DESC');

        if ($search !== '') {
            $qb->andWhere('sr.title LIKE :search OR w.firstName LIKE :search OR w.lastName LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($statusFilter !== '' && $statusFilter !== 'all') {
            $qb->andWhere('o.status = :status')
               ->setParameter('status', strtoupper($statusFilter));
        }

        $totalCountQb = clone $qb;
        $total = (int) $totalCountQb->select('COUNT(DISTINCT o.id)')->getQuery()->getSingleScalarResult();
        $totalPages = max(1, (int) ceil($total / $limit));

        $query = $qb->setFirstResult(($page - 1) * $limit)->setMaxResults($limit)->getQuery();
        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, true);
        $offers = iterator_to_array($paginator);

        // Offer stats aggregation - only if not an AJAX request to save performance
        $conversionRate = 0;
        $averagePrice = 0;
        $statusCounts = ['pending' => 0, 'accepted' => 0, 'rejected' => 0, 'expired' => 0];

        if (!$request->isXmlHttpRequest()) {
            $allOffersCount = $this->offerRepository->count([]);
            $statusCounts['pending']  = $this->offerRepository->count(['status' => 'PENDING']);
            $statusCounts['rejected'] = $this->offerRepository->count(['status' => 'REJECTED']);
            $statusCounts['expired']  = $this->offerRepository->count(['status' => 'EXPIRED']);

            // Use aggregate query instead of loading all accepted offer objects
            $acceptedAgg = $this->offerRepository->createQueryBuilder('o')
                ->select('COUNT(o.id) AS cnt, AVG(o.price) AS avg_price')
                ->where('o.status = :st')
                ->setParameter('st', 'ACCEPTED')
                ->getQuery()
                ->getSingleResult();

            $acceptedCount = (int) ($acceptedAgg['cnt'] ?? 0);
            $statusCounts['accepted'] = $acceptedCount;
            $conversionRate = $allOffersCount > 0 ? round(($acceptedCount / $allOffersCount) * 100, 2) : 0;
            $averagePrice   = round((float) ($acceptedAgg['avg_price'] ?? 0), 2);
        }

        $template = $request->isXmlHttpRequest()
            ? 'pages/admin/_offers_list_table.html.twig'
            : 'pages/admin/offers_list.html.twig';

        return $this->render($template, [
            'offers' => $offers,
            'search_keyword' => $search,
            'status_filter' => $statusFilter,
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
            'topbar_title' => 'Offers Management',
            'user_name' => $this->getUser()?->getUsername() ?? 'Admin',
            'notification_count' => 0,
            'sidebar_items' => $this->getAdminSidebarItems('offers'),
            'offer_stats' => [
                'pending' => $statusCounts['pending'],
                'accepted' => $statusCounts['accepted'],
                'rejected' => $statusCounts['rejected'],
                'expired' => $statusCounts['expired'],
                'conversion_rate' => $conversionRate,
                'average_price' => $averagePrice,
            ],
        ]);
    }

    /* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ SHOW â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    /* ──────────────── SHOW ──────────────── */
    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Offer $offer): Response
    {
        return $this->render('pages/admin/offer_show.html.twig', [
            'offer' => $offer,
            'topbar_title' => 'Offer Details',
            'user_name' => $this->getUser()?->getFullName() ?? 'Admin',
            'notification_count' => 0,
            'sidebar_items' => $this->getAdminSidebarItems('offers'),
        ]);
    }

    /* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ SELECT SERVICE REQUEST â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    /* ──────────────── SELECT SERVICE REQUEST ──────────────── */
    #[Route('/select-request', name: 'select_request', methods: ['GET'])]
    public function selectRequest(Request $request): Response
    {
        $search = trim((string) $request->query->get('search', ''));
        $statusFilter = $request->query->get('status', '');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;

        $qb = $this->serviceRequestRepository->createQueryBuilder('sr')
            ->leftJoin('sr.client', 'c')
            ->orderBy('sr.createdAt', 'DESC');

        if ($search !== '') {
            $qb->andWhere('sr.title LIKE :search OR c.firstName LIKE :search OR c.lastName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($statusFilter !== '' && $statusFilter !== 'all') {
            $qb->andWhere('sr.status = :status')
                ->setParameter('status', strtoupper($statusFilter));
        }

        $total = (int) (clone $qb)->select('COUNT(DISTINCT sr.id)')->getQuery()->getSingleScalarResult();
        $totalPages = max(1, (int) ceil($total / $limit));

        $query = $qb->setFirstResult(($page - 1) * $limit)->setMaxResults($limit)->getQuery();
        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, true);
        $serviceRequests = iterator_to_array($paginator);

        return $this->render('pages/admin/offer_select_request.html.twig', [
            'service_requests' => $serviceRequests,
            'search_keyword' => $search,
            'status_filter' => $statusFilter,
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
            'topbar_title' => 'Select Service Request',
            'user_name' => $this->getUser()?->getFullName() ?? 'Admin',
            'notification_count' => 0,
            'sidebar_items' => $this->getAdminSidebarItems('offers'),
        ]);
    }

    /* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ NEW (reply to a service request) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    /* ──────────────── NEW (reply to a service request) ──────────────── */
    #[Route('/new/{serviceRequest}', name: 'new', requirements: ['serviceRequest' => '\d+'], methods: ['GET', 'POST'])]
    public function new(Request $request, ServiceRequest $serviceRequest): Response
    {
        $offer = new Offer();
        $offer->setStatus('PENDING');
        $offer->setPriorityLevel('MEDIUM');
        $offer->setServiceRequest($serviceRequest);
        $offer->setWorker($this->getUser());

        $form = $this->createForm(OfferType::class, $offer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($offer);
            $this->entityManager->flush();

            $this->addFlash('success', 'Offer submitted successfully.');

            return $this->redirectToRoute('admin_offers_index');
        }

        return $this->render('pages/admin/offer_new.html.twig', [
            'offer' => $offer,
            'service_request' => $serviceRequest,
            'form' => $form,
            'topbar_title' => 'Submit Offer',
            'user_name' => $this->getUser()?->getFullName() ?? 'Admin',
            'notification_count' => 0,
            'sidebar_items' => $this->getAdminSidebarItems('offers'),
        ]);
    }

    /* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ EDIT â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    /* ──────────────── EDIT ──────────────── */
    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Offer $offer): Response
    {
        $form = $this->createForm(OfferType::class, $offer, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Offer updated successfully.');

            return $this->redirectToRoute('admin_offers_show', ['id' => $offer->getId()]);
        }

        return $this->render('pages/admin/offer_edit.html.twig', [
            'offer' => $offer,
            'form' => $form,
            'topbar_title' => 'Edit Offer',
            'user_name' => $this->getUser()?->getFullName() ?? 'Admin',
            'notification_count' => 0,
            'sidebar_items' => $this->getAdminSidebarItems('offers'),
        ]);
    }

    /* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ DELETE â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    /* ──────────────── DELETE ──────────────── */
    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Offer $offer): Response
    {
        $this->entityManager->remove($offer);
        $this->entityManager->flush();

        $this->addFlash('success', 'Offer deleted successfully.');

        return $this->redirectToRoute('admin_offers_index');
    }

    /* ──────────────── ANALYTICS ──────────────── */

    #[Route('/stats/funnel', name: 'stats_funnel', methods: ['GET'])]
    public function funnelStats(): JsonResponse
    {
        return new JsonResponse($this->analyticsService->getFunnelData());
    }

    #[Route('/stats/acceptance-trend', name: 'stats_acceptance_trend', methods: ['GET'])]
    public function acceptanceTrendStats(Request $request): JsonResponse
    {
        $days = (int) $request->query->get('days', 14);
        return new JsonResponse($this->analyticsService->getAcceptanceTrend($days));
    }

    #[Route('/stats/ai-impact', name: 'stats_ai_impact', methods: ['GET'])]
    public function aiImpactStats(): JsonResponse
    {
        return new JsonResponse($this->analyticsService->getAiImpactStats());
    }

    /* ──────────────── SIDEBAR ──────────────── */

    private function getAdminSidebarItems(string $active = ''): array
    {
        return [
            [
                'label' => 'Dashboard',
                'url' => $this->generateUrl('admin_dashboard'),
                'active' => $active === 'dashboard',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>',
            ],
            [
                'label' => 'User Management',
                'url' => $this->generateUrl('admin_users_index'),
                'active' => $active === 'users',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>',
            ],
            [
                'label' => 'Offers Management',
                'url' => $this->generateUrl('admin_offers_index'),
                'active' => $active === 'offers',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>',
            ],
            [
                'label' => 'Service Management',
                'url' => $this->generateUrl('admin_services_index'),
                'active' => $active === 'services',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
            ],
            [
                'label' => 'Contracts',
                'url' => $this->generateUrl('admin_contracts_index'),
                'active' => $active === 'contracts',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
            ],
            [
                'label' => 'Tickets',
                'url' => $this->generateUrl('admin_ticket_list'),
                'active' => $active === 'tickets',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>',
            ],
            [
                'label' => 'Worker Categories',
                'url' => $this->generateUrl('admin_categories_index'),
                'active' => $active === 'worker_categories',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>',
            ],
            [
                'label' => 'Ticket Categories',
                'url' => $this->generateUrl('admin_category_ticket_index'),
                'active' => $active === 'ticket_categories',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>',
            ],
            [
                'label' => 'Certificates',
                'url' => $this->generateUrl('admin_certificates_index'),
                'active' => $active === 'certificates',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>',
            ],
            [
                'label' => 'Face Auth Logs',
                'url' => $this->generateUrl('admin_face_logs'),
                'active' => $active === 'face_auth',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>',
            ],
            [
                'label' => 'System Settings',
                'url' => $this->generateUrl('admin_dashboard'),
                'active' => $active === 'settings',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
            ],
        ];
    }
}
