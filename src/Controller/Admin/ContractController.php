<?php

namespace App\Controller\Admin;

use App\Entity\Contract;
use App\Repository\ContractRepository;
use App\Repository\UserRepository;
use App\Service\ContractPdfService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/contracts', name: 'admin_contracts_')]
#[IsGranted('ROLE_ADMIN')]
class ContractController extends BaseController
{
    public function __construct(
        private ContractRepository $contractRepository,
        private EntityManagerInterface $em,
    ) {}

    /* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ LIST â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    /* ──────────────── LIST ──────────────── */

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $keyword = trim((string) $request->query->get('search', ''));
        $statusFilter = $request->query->get('status', '');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 15;
        $offset = ($page - 1) * $limit;

        $qb = $this->contractRepository->createQueryBuilder('c')
            ->leftJoin('c.client', 'cl')
            ->leftJoin('c.worker', 'w')
            ->addSelect('cl', 'w')
            ->orderBy('c.createdAt', 'DESC');

        if ($keyword !== '') {
            $qb->andWhere('c.title LIKE :kw OR cl.firstName LIKE :kw OR cl.lastName LIKE :kw OR w.firstName LIKE :kw OR w.lastName LIKE :kw OR cl.email LIKE :kw OR w.email LIKE :kw')
               ->setParameter('kw', '%' . $keyword . '%');
        }

        if ($statusFilter !== '' && in_array($statusFilter, Contract::STATUSES, true)) {
            $qb->andWhere('c.status = :status')
               ->setParameter('status', $statusFilter);
        }

        $total = (int) (clone $qb)->select('COUNT(DISTINCT c.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();
        $totalPages = (int) ceil($total / $limit);

        $query = $qb->setFirstResult($offset)->setMaxResults($limit)->getQuery();
        $paginator = new Paginator($query, true);
        $contracts = iterator_to_array($paginator);

        return $this->render('pages/admin/contracts/index.html.twig', [
            'contracts' => $contracts,
            'search_keyword' => $keyword,
            'status_filter' => $statusFilter,
            'statuses' => Contract::STATUSES,
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
            'topbar_title' => 'Contract Management',
            'sidebar_items' => $this->getAdminSidebarItems('contracts'),
        ]);
    }

    /* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ CREATE (form) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    /* ──────────────── CREATE (form) ──────────────── */

    #[Route('/create', name: 'create', methods: ['GET'])]
    public function create(UserRepository $userRepository): Response
    {
        $clients = $userRepository->createQueryBuilder('u')
            ->where('u.role = :role')->setParameter('role', 'CLIENT')
            ->orderBy('u.firstName', 'ASC')->setMaxResults(200)->getQuery()->getResult();
        $workers = $userRepository->createQueryBuilder('u')
            ->where('u.role = :role')->setParameter('role', 'WORKER')
            ->orderBy('u.firstName', 'ASC')->setMaxResults(200)->getQuery()->getResult();

        return $this->render('pages/admin/contracts/create.html.twig', [
            'clients' => $clients,
            'workers' => $workers,
            'statuses' => Contract::STATUSES,
            'errors' => [],
            'topbar_title' => 'Create Contract',
            'sidebar_items' => $this->getAdminSidebarItems('contracts'),
        ]);
    }

    /* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ STORE (POST) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    /* ──────────────── STORE (POST) ──────────────── */

    #[Route('/store', name: 'store', methods: ['POST'])]
    public function store(Request $request, UserRepository $userRepository): Response
    {
        $title     = trim($request->request->get('title', ''));
        $scope     = trim($request->request->get('scope', ''));
        $price     = $request->request->get('agreed_price', '');
        $currency  = $request->request->get('currency', 'USD');
        $startDate = $request->request->get('start_date', '');
        $endDate   = $request->request->get('end_date', '');
        $status    = $request->request->get('status', Contract::STATUS_DRAFT);
        $clientId  = $request->request->get('client_id', '');
        $workerId  = $request->request->get('worker_id', '');

        $errors = [];

        // Title
        if ($title === '') {
            $errors['title'] = 'Title is required.';
        } elseif (mb_strlen($title) < 3) {
            $errors['title'] = 'Title must be at least 3 characters.';
        } elseif (mb_strlen($title) > 255) {
            $errors['title'] = 'Title must not exceed 255 characters.';
        }

        // Client
        $client = $clientId !== '' ? $userRepository->find((int) $clientId) : null;
        if (!$client) {
            $errors['client_id'] = 'Please select a valid client.';
        }

        // Worker
        $worker = $workerId !== '' ? $userRepository->find((int) $workerId) : null;
        if (!$worker) {
            $errors['worker_id'] = 'Please select a valid worker.';
        }

        // Price
        if ($price === '') {
            $errors['agreed_price'] = 'Price is required.';
        } elseif (!is_numeric($price) || (float) $price < 1) {
            $errors['agreed_price'] = 'Price must be at least 1.';
        } elseif ((float) $price > 9999999.99) {
            $errors['agreed_price'] = 'Price must not exceed 9,999,999.99.';
        }

        // Dates
        if ($startDate === '' || !strtotime($startDate)) {
            $errors['start_date'] = 'Start date is invalid.';
        }
        if ($endDate === '' || !strtotime($endDate)) {
            $errors['end_date'] = 'End date is invalid.';
        }
        if (empty($errors['start_date']) && empty($errors['end_date']) && strtotime($endDate) <= strtotime($startDate)) {
            $errors['end_date'] = 'End date must be after start date.';
        }

        // Status
        if (!in_array($status, Contract::STATUSES, true)) {
            $errors['status'] = 'Invalid status.';
        }

        // Scope
        if ($scope === '') {
            $errors['scope'] = 'Scope of work is required.';
        } elseif (mb_strlen($scope) < 10) {
            $errors['scope'] = 'Scope must be at least 10 characters.';
        }

        // Currency
        if (!in_array($currency, ['USD', 'EUR', 'TND', 'MAD'], true)) {
            $errors['currency'] = 'Invalid currency.';
        }

        if (!empty($errors)) {
            $clients = $userRepository->createQueryBuilder('u')
                ->where('u.role = :role')->setParameter('role', 'CLIENT')
                ->orderBy('u.firstName', 'ASC')->setMaxResults(200)->getQuery()->getResult();
            $workers = $userRepository->createQueryBuilder('u')
                ->where('u.role = :role')->setParameter('role', 'WORKER')
                ->orderBy('u.firstName', 'ASC')->setMaxResults(200)->getQuery()->getResult();

            return $this->render('pages/admin/contracts/create.html.twig', [
                'clients' => $clients,
                'workers' => $workers,
                'statuses' => Contract::STATUSES,
                'errors' => $errors,
                'topbar_title' => 'Create Contract',
                'sidebar_items' => $this->getAdminSidebarItems('contracts'),
            ]);
        }

        $contract = new Contract();
        $contract->setTitle($title);
        $contract->setScope($scope);
        $contract->setAgreedPrice($price);
        $contract->setCurrency($currency);
        $contract->setStartDate(new \DateTime($startDate));
        $contract->setEndDate(new \DateTime($endDate));
        $contract->setStatus($status);
        $contract->setClient($client);
        $contract->setWorker($worker);

        $this->em->persist($contract);
        $this->em->flush();

        $this->addFlash('success', 'Contract created successfully.');
        return $this->redirectToRoute('admin_contracts_show', ['id' => $contract->getId()]);
    }

    /* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ SHOW â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    /* ──────────────── SHOW ──────────────── */

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Contract $contract): Response
    {
        return $this->render('pages/admin/contracts/show.html.twig', [
            'contract' => $contract,
            'topbar_title' => 'Contract #' . $contract->getId(),
            'sidebar_items' => $this->getAdminSidebarItems('contracts'),
        ]);
    }

    /* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ PDF â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    /* ──────────────── PDF ──────────────── */

    #[Route('/{id}/pdf', name: 'pdf', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function pdf(Contract $contract, ContractPdfService $pdfService): Response
    {
        $pdfContent = $pdfService->generate($contract);

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="contract_%d.pdf"', $contract->getId()),
        ]);
    }

    /* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ EDIT (form) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    /* ──────────────── EDIT (form) ──────────────── */

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function edit(Contract $contract): Response
    {
        return $this->render('pages/admin/contracts/edit.html.twig', [
            'contract' => $contract,
            'statuses' => Contract::STATUSES,
            'errors' => [],
            'topbar_title' => 'Edit Contract #' . $contract->getId(),
            'sidebar_items' => $this->getAdminSidebarItems('contracts'),
        ]);
    }

    /* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ UPDATE (POST) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    /* ──────────────── UPDATE (POST) ──────────────── */

    #[Route('/{id}/update', name: 'update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(Contract $contract, Request $request, UserRepository $userRepository): Response
    {
        $title     = trim($request->request->get('title', ''));
        $scope     = trim($request->request->get('scope', ''));
        $price     = $request->request->get('agreed_price', '');
        $currency  = $request->request->get('currency', 'USD');
        $startDate = $request->request->get('start_date', '');
        $endDate   = $request->request->get('end_date', '');
        $status    = $request->request->get('status', '');

        $errors = [];

        // Title
        if ($title === '') {
            $errors['title'] = 'Le titre est obligatoire.';
        } elseif (mb_strlen($title) < 3) {
            $errors['title'] = 'Le titre doit contenir au moins 3 caractères.';
        } elseif (mb_strlen($title) > 255) {
            $errors['title'] = 'Le titre ne doit pas dépasser 255 caractères.';
        }

        // Price
        if ($price === '') {
            $errors['agreed_price'] = 'Le prix est obligatoire.';
        } elseif (!is_numeric($price) || (float) $price < 1) {
            $errors['agreed_price'] = 'Le prix doit être au moins 1.';
        } elseif ((float) $price > 9999999.99) {
            $errors['agreed_price'] = 'Le prix ne peut pas dépasser 9 999 999,99.';
        }

        // Dates
        if ($startDate === '' || !strtotime($startDate)) {
            $errors['start_date'] = 'La date de début est invalide.';
        }
        if ($endDate === '' || !strtotime($endDate)) {
            $errors['end_date'] = 'La date de fin est invalide.';
        }
        if (empty($errors['start_date']) && empty($errors['end_date']) && strtotime($endDate) <= strtotime($startDate)) {
            $errors['end_date'] = 'La date de fin doit être après la date de début.';
        }

        // Status
        if (!in_array($status, Contract::STATUSES, true)) {
            $errors['status'] = 'Statut invalide.';
        }

        // Scope
        if ($scope === '') {
            $errors['scope'] = 'La description est obligatoire.';
        } elseif (mb_strlen($scope) < 10) {
            $errors['scope'] = 'La description doit contenir au moins 10 caractères.';
        }

        // Currency
        if (!in_array($currency, ['USD', 'EUR', 'TND', 'MAD'], true)) {
            $errors['currency'] = 'Devise invalide.';
        }

        if (!empty($errors)) {
            return $this->render('pages/admin/contracts/edit.html.twig', [
                'contract' => $contract,
                'statuses' => Contract::STATUSES,
                'errors' => $errors,
                'topbar_title' => 'Edit Contract #' . $contract->getId(),
                'sidebar_items' => $this->getAdminSidebarItems('contracts'),
            ]);
        }

        $contract->setTitle($title);
        $contract->setScope($scope);
        $contract->setAgreedPrice($price);
        $contract->setCurrency($currency);
        $contract->setStartDate(new \DateTime($startDate));
        $contract->setEndDate(new \DateTime($endDate));
        $contract->setStatus($status);

        $this->em->flush();

        $this->addFlash('success', 'Contract updated successfully.');
        return $this->redirectToRoute('admin_contracts_show', ['id' => $contract->getId()]);
    }

    /* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ DELETE â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    /* ──────────────── DELETE ──────────────── */

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Contract $contract): Response
    {
        $this->em->remove($contract);
        $this->em->flush();

        $this->addFlash('success', 'Contract deleted successfully.');
        return $this->redirectToRoute('admin_contracts_index');
    }

    /* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ SIDEBAR â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    /* ──────────────── SIDEBAR ──────────────── */

    private function getAdminSidebarItems(string $active = ''): array
    {
        return [
            [
                'label' => 'Dashboard',
                'url' => $this->generateUrl('admin_dashboard'),
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>',
                'active' => $active === 'dashboard',
            ],
            [
                'label' => 'User Management',
                'url' => $this->generateUrl('admin_users_index'),
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>',
                'active' => $active === 'users',
            ],
            [
                'label' => 'Contracts',
                'url' => $this->generateUrl('admin_contracts_index'),
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
                'active' => $active === 'contracts',
            ],
            [
                'label' => 'Tickets',
                'url' => $this->generateUrl('admin_ticket_list'),
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h6m-6 4h10M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H7l-4 4v10a2 2 0 002 2z"/></svg>',
                'active' => $active === 'tickets',
            ],
            [
                'label' => 'Ticket Categories',
                'url' => $this->generateUrl('admin_category_ticket_index'),
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>',
                'active' => $active === 'ticket_categories',
            ],
            [
                'label' => 'Certificates',
                'url' => $this->generateUrl('admin_certificates_index'),
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
                'active' => $active === 'certificates',
            ],
            [
                'label' => 'Face Auth Logs',
                'url' => $this->generateUrl('admin_face_logs'),
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>',
                'active' => $active === 'face_logs',
            ],
        ];
    }
}
