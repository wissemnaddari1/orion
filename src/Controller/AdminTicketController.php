<?php

namespace App\Controller;

use App\Entity\Ticket;
use App\Entity\SubTicket;
use App\Form\AdminTicketEditType;
use App\Form\AdminTicketType;
use App\Form\SubTicketType;
use App\Repository\TicketRepository;
use App\Repository\SubTicketRepository;
use App\Repository\CategoryTicketRepository;
use App\Repository\UserRepository;
use App\Service\TicketAiInsightService;
use App\Service\MessageModerationService;
use App\Service\TicketSupportAIService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Admin Ticket Controller
 * Full CRUD for ticket management (Admin only)
 * Implements strict access control
 */
#[Route('/admin/tickets')]
#[IsGranted('ROLE_ADMIN')]
class AdminTicketController extends AbstractController
{
    public function __construct(
        private TicketAiInsightService $ticketAiInsightService,
        private TicketSupportAIService $ticketSupportAIService,
        private MessageModerationService $messageModerationService,
    ) {
    }

    /**
     * List all tickets with filters
     */
    #[Route('/', name: 'admin_ticket_list', methods: ['GET'])]
    public function index(
        Request $request,
        TicketRepository $ticketRepository,
        CategoryTicketRepository $categoryRepository
    ): Response {
        $filters = [
            'search' => $request->query->get('search'),
            'status' => $request->query->get('status'),
            'priority' => $request->query->get('priority'),
            'category' => $request->query->get('category'),
            'acknowledged' => $request->query->get('acknowledged'),
        ];

        $filters = array_filter($filters, fn($value) => $value !== null && $value !== '');

        $page = max(1, (int) $request->query->get('page', 1));
        $pagination = $ticketRepository->paginate($page, 20, $filters);
        $tickets = $pagination['items'];
        $stats = $ticketRepository->getStatistics();
        $categories = $categoryRepository->findAllOrdered();

        return $this->render('admin/ticket/index.html.twig', [
            'tickets' => $tickets,
            'stats' => $stats,
            'categories' => $categories,
            'filters' => $filters,
            'page' => $pagination['page'],
            'pages' => $pagination['pages'],
            'total' => $pagination['total'],
            'sidebar_items' => $this->getAdminSidebarItems('tickets'),
            'topbar_title' => 'Ticket Management',
            'user_name' => $this->getUser()?->getFullName() ?? 'Admin',
            'notification_count' => 0,
        ]);
    }

    /**
     * Create a new ticket as admin (for a client/worker)
     */
    #[Route('/create', name: 'admin_ticket_create', methods: ['GET', 'POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $ticket = new Ticket();
        $form = $this->createForm(AdminTicketType::class, $ticket);
        $form->handleRequest($request);

        // SERVER-SIDE VALIDATION (controle de saisie)
        if ($form->isSubmitted() && $form->isValid()) {
            $adminUser = $this->getUser();

            $ticket->setStatus('OPEN');
            $ticket->setMessageCount(1);
            $ticket->setLastMessageAt(new \DateTime());
            $ticket->setAcknowledgedByAd(true);
            $ticket->setAcknowledgedAt(new \DateTime());

            $subTicket = new SubTicket();
            $subTicket->setTicket($ticket);
            $subTicket->setSender($adminUser);
            $subTicket->setMessage($form->get('message')->getData());
            $subTicket->setSenderRole('ADMIN');
            $subTicket->setIsInternal(false);
            $subTicket->setIsRead(false);
            $subTicket->setIsEdited(false);
            $subTicket->setIsDeleted(false);

            $attachmentFile = $form->get('attachment')->getData();
            if ($attachmentFile) {
                try {
                    // Get all metadata BEFORE any file operations
                    $clientOriginalName = $attachmentFile->getClientOriginalName();
                    $clientMimeType = $attachmentFile->getClientMimeType();
                    $clientSize = $attachmentFile->getSize();
                    
                    $originalFilename = pathinfo($clientOriginalName, PATHINFO_FILENAME);
                    $extension = pathinfo($clientOriginalName, PATHINFO_EXTENSION);
                    
                    if (empty($extension)) {
                        throw new \Exception('File must have an extension');
                    }
                    
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$extension;
                    
                    $uploadsDirectory = $this->getParameter('kernel.project_dir').'/public/uploads/tickets';
                    
                    // Ensure directory exists
                    if (!is_dir($uploadsDirectory)) {
                        mkdir($uploadsDirectory, 0777, true);
                    }

                    // Move file immediately
                    $attachmentFile->move($uploadsDirectory, $newFilename);

                    $subTicket->setFileName($originalFilename.'.'.$extension);
                    $subTicket->setFilePath('/uploads/tickets/'.$newFilename);
                    $subTicket->setFileType($clientMimeType);
                    $subTicket->setFileSize($clientSize);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'File upload failed: ' . $e->getMessage());
                }
            }

            $aiInsight = $this->ticketAiInsightService->analyzeTicket(
                (string) $ticket->getSubject(),
                (string) $subTicket->getMessage(),
            );

            if (null !== $aiInsight) {
                $ticket->setAiSentiment($aiInsight['sentiment']);
                $ticket->setAiUrgency($aiInsight['urgency']);
                $ticket->setAiSuggestedPriority($aiInsight['suggested_priority']);
                $ticket->setAiSummary($aiInsight['short_summary']);
            }

            $entityManager->persist($ticket);
            $entityManager->persist($subTicket);
            $entityManager->flush();

            $this->addFlash('success', 'Ticket created successfully.');

            return $this->redirectToRoute('admin_ticket_view', ['id' => $ticket->getId()]);
        }

        return $this->render('admin/ticket/create.html.twig', [
            'form' => $form->createView(),
            'sidebar_items' => $this->getAdminSidebarItems('tickets'),
            'topbar_title' => 'Create Ticket',
            'user_name' => $this->getUser()?->getFullName() ?? 'Admin',
            'notification_count' => 0,
        ]);
    }

    /**
     * Search users by username (AJAX endpoint for ticket creation)
     */
    #[Route('/search-users', name: 'admin_ticket_search_users', methods: ['GET'])]
    public function searchUsers(Request $request, UserRepository $userRepository): JsonResponse
    {
        $query = $request->query->get('q', '');

        if (strlen($query) < 2) {
            return $this->json([]);
        }

        $users = $userRepository->createQueryBuilder('u')
            ->where('u.username LIKE :query OR u.firstName LIKE :query OR u.lastName LIKE :query OR u.email LIKE :query')
            ->andWhere('u.role IN (:roles)')
            ->andWhere('u.status = :status')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('roles', ['CLIENT', 'WORKER'])
            ->setParameter('status', 'ACTIVE')
            ->setMaxResults(10)
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();

        $results = array_map(function ($user) {
            return [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'fullName' => $user->getFirstName() . ' ' . $user->getLastName(),
                'email' => $user->getEmail(),
                'role' => $user->getRole(),
                'label' => sprintf(
                    '%s - %s %s (%s)',
                    $user->getUsername(),
                    $user->getFirstName(),
                    $user->getLastName(),
                    $user->getEmail()
                )
            ];
        }, $users);

        return $this->json($results);
    }

    /**
     * Edit ticket details (admin)
     */
    #[Route('/{id}/edit', name: 'admin_ticket_edit', methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        TicketRepository $ticketRepository,
        SubTicketRepository $subTicketRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $ticket = $ticketRepository->find($id);

        if (!$ticket) {
            throw $this->createNotFoundException('Ticket not found.');
        }

        $previousStatus = $ticket->getStatus();
        $form = $this->createForm(AdminTicketEditType::class, $ticket);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newStatus = $ticket->getStatus();

            if ($newStatus === 'CLOSED' && $previousStatus !== 'CLOSED') {
                $ticket->setClosedAt(new \DateTime());
            }

            if ($newStatus !== 'CLOSED' && $previousStatus === 'CLOSED') {
                $ticket->setClosedAt(null);
            }

            $entityManager->flush();

            if ($newStatus === 'CLOSED' && $ticket->getResolution()) {
                $this->syncTicketToAiKnowledgeBase($ticket, $subTicketRepository);
            }

            $this->addFlash('success', 'Ticket updated successfully.');

            return $this->redirectToRoute('admin_ticket_view', ['id' => $ticket->getId()]);
        }

        return $this->render('admin/ticket/edit.html.twig', [
            'form' => $form->createView(),
            'ticket' => $ticket,
            'sidebar_items' => $this->getAdminSidebarItems('tickets'),
            'topbar_title' => 'Edit Ticket',
            'user_name' => $this->getUser()?->getFullName() ?? 'Admin',
            'notification_count' => 0,
        ]);
    }

    /**
     * View ticket details (admin can see all messages including internal notes)
     */
    #[Route('/{id}', name: 'admin_ticket_view', methods: ['GET'])]
    public function view(
        int $id,
        TicketRepository $ticketRepository,
        SubTicketRepository $subTicketRepository
    ): Response {
        $ticket = $ticketRepository->find($id);

        if (!$ticket) {
            throw $this->createNotFoundException('Ticket not found.');
        }

        $messages = $subTicketRepository->findByTicket($id, true);
        $firstMessage = $subTicketRepository->findBy(['ticket' => $ticket], ['createdAt' => 'ASC'], 1);
        $createdByAdmin = !empty($firstMessage) && $firstMessage[0]->getSenderRole() === 'ADMIN';
        $replyForm = $this->createForm(SubTicketType::class, null, [
            'is_admin' => true
        ]);

        return $this->render('admin/ticket/view.html.twig', [
            'ticket' => $ticket,
            'messages' => $messages,
            'replyForm' => $replyForm->createView(),
            'created_by_admin' => $createdByAdmin,
            'sidebar_items' => $this->getAdminSidebarItems('tickets'),
            'topbar_title' => 'Ticket Details',
            'user_name' => $this->getUser()?->getFullName() ?? 'Admin',
            'notification_count' => 0,
        ]);
    }

    /**
     * Reply to ticket (admin can add internal notes)
     */
    #[Route('/{id}/reply', name: 'admin_ticket_reply', methods: ['POST'])]
    public function reply(
        int $id,
        Request $request,
        TicketRepository $ticketRepository,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $ticket = $ticketRepository->find($id);

        if (!$ticket) {
            throw $this->createNotFoundException('Ticket not found.');
        }

        $user = $this->getUser();
        $form = $this->createForm(SubTicketType::class, null, [
            'is_admin' => true
        ]);
        $form->handleRequest($request);

        // SERVER-SIDE VALIDATION
        if ($form->isSubmitted() && $form->isValid()) {
            $messageText = $form->get('message')->getData();

            // Moderation check ÔÇö block toxic messages
            $moderation = $this->messageModerationService->moderateMessage($messageText);
            if ($moderation['is_toxic']) {
                if ($request->isXmlHttpRequest()) {
                    return $this->json(['success' => false, 'error' => 'Your message contains inappropriate content and cannot be sent. Please revise your message.'], 400);
                }
                $this->addFlash('error', 'Your message contains inappropriate content and cannot be sent. Please revise your message.');
                return $this->redirectToRoute('admin_ticket_view', ['id' => $id]);
            }

            $subTicket = new SubTicket();
            $subTicket->setTicket($ticket);
            $subTicket->setSender($user);
            $subTicket->setMessage($messageText);
            $subTicket->setSenderRole('ADMIN');

            $isInternal = $form->has('isInternal') && $form->get('isInternal')->getData();
            $subTicket->setIsInternal($isInternal);

            $subTicket->setIsRead(false);
            $subTicket->setIsEdited(false);
            $subTicket->setIsDeleted(false);

            $attachmentFile = $form->get('attachment')->getData();
            if ($attachmentFile) {
                try {
                    // Get all metadata BEFORE any file operations
                    $clientOriginalName = $attachmentFile->getClientOriginalName();
                    $clientMimeType = $attachmentFile->getClientMimeType();
                    $clientSize = $attachmentFile->getSize();
                    
                    $originalFilename = pathinfo($clientOriginalName, PATHINFO_FILENAME);
                    $extension = pathinfo($clientOriginalName, PATHINFO_EXTENSION);
                    
                    if (empty($extension)) {
                        throw new \Exception('File must have an extension');
                    }
                    
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$extension;
                    
                    $uploadsDirectory = $this->getParameter('kernel.project_dir').'/public/uploads/tickets';
                    
                    // Ensure directory exists
                    if (!is_dir($uploadsDirectory)) {
                        mkdir($uploadsDirectory, 0777, true);
                    }

                    // Move file immediately
                    $attachmentFile->move($uploadsDirectory, $newFilename);

                    $subTicket->setFileName($originalFilename.'.'.$extension);
                    $subTicket->setFilePath('/uploads/tickets/'.$newFilename);
                    $subTicket->setFileType($clientMimeType);
                    $subTicket->setFileSize($clientSize);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'File upload failed: ' . $e->getMessage());
                }
            }

            if (!$isInternal) {
                $ticket->setLastMessageAt(new \DateTime());
                $ticket->setMessageCount($ticket->getMessageCount() + 1);

                if ($ticket->getStatus() === 'IN_PROGRESS' || $ticket->getStatus() === 'OPEN') {
                    $ticket->setStatus('WAITING_USER');
                }
            }

            $entityManager->persist($subTicket);
            $entityManager->flush();

            $msg = $isInternal ? 'Internal note added.' : 'Reply sent to user.';
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => true, 'message' => $msg]);
            }
            $this->addFlash('success', $msg);

            return $this->redirectToRoute('admin_ticket_view', ['id' => $id]);
        }

        return $this->redirectToRoute('admin_ticket_view', ['id' => $id]);
    }

    /**
     * Acknowledge ticket (mark as seen by admin)
     */
    #[Route('/{id}/acknowledge', name: 'admin_ticket_acknowledge', methods: ['POST'])]
    public function acknowledge(
        int $id,
        TicketRepository $ticketRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $ticket = $ticketRepository->find($id);

        if (!$ticket) {
            throw $this->createNotFoundException('Ticket not found.');
        }

        if (!$ticket->isAcknowledgedByAd()) {
            $ticket->setAcknowledgedByAd(true);
            $ticket->setAcknowledgedAt(new \DateTime());

            if ($ticket->getStatus() === 'OPEN') {
                $ticket->setStatus('IN_PROGRESS');
            }

            $entityManager->flush();
            $this->addFlash('success', 'Ticket acknowledged.');
        }

        return $this->redirectToRoute('admin_ticket_view', ['id' => $id]);
    }

    /**
     * Change ticket status
     */
    #[Route('/{id}/status', name: 'admin_ticket_status', methods: ['POST'])]
    public function changeStatus(
        int $id,
        Request $request,
        TicketRepository $ticketRepository,
        SubTicketRepository $subTicketRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $ticket = $ticketRepository->find($id);

        if (!$ticket) {
            throw $this->createNotFoundException('Ticket not found.');
        }

        $previousStatus = $ticket->getStatus();
        $newStatus = $request->request->get('status');
        $allowedStatuses = ['OPEN', 'IN_PROGRESS', 'WAITING_USER', 'CLOSED'];

        if (!in_array($newStatus, $allowedStatuses, true)) {
            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid status value.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $this->addFlash('error', 'Invalid status value.');
            return $this->redirectToRoute('admin_ticket_view', ['id' => $id]);
        }

        if ($previousStatus === 'CLOSED' && $newStatus !== 'CLOSED' && $request->isXmlHttpRequest()) {
            return $this->json([
                'success' => false,
                'error' => 'Closed tickets cannot be moved back to another column.',
            ], Response::HTTP_FORBIDDEN);
        }

        $ticket->setStatus($newStatus);

        if ($newStatus === 'CLOSED' && $previousStatus !== 'CLOSED') {
            $ticket->setClosedAt(new \DateTime());
            $resolution = $request->request->get('resolution');
            if ($resolution) {
                $ticket->setResolution($resolution);
            }
        }

        if ($newStatus !== 'CLOSED' && $previousStatus === 'CLOSED') {
            $ticket->setClosedAt(null);
        }

        $entityManager->flush();

        if ($newStatus === 'CLOSED' && $ticket->getResolution()) {
            $this->syncTicketToAiKnowledgeBase($ticket, $subTicketRepository);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'success' => true,
                'ticketId' => $ticket->getId(),
                'status' => $ticket->getStatus(),
            ]);
        }

        $this->addFlash('success', 'Ticket status updated to ' . $newStatus);

        return $this->redirectToRoute('admin_ticket_view', ['id' => $id]);
    }

    /**
     * Delete ticket (soft delete recommended, or hard delete if needed)
     */
    #[Route('/{id}/delete', name: 'admin_ticket_delete', methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        TicketRepository $ticketRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $ticket = $ticketRepository->find($id);

        if (!$ticket) {
            throw $this->createNotFoundException('Ticket not found.');
        }

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_ticket_' . $ticket->getId(), $token)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('admin_ticket_list');
        }

        foreach ($ticket->getSubTickets() as $subTicket) {
            $entityManager->remove($subTicket);
        }

        $entityManager->remove($ticket);
        $entityManager->flush();

        $this->addFlash('success', 'Ticket deleted permanently.');

        return $this->redirectToRoute('admin_ticket_list');
    }

    /**
     * Sync a closed ticket to the AI knowledge base for future suggestions.
     */
    private function syncTicketToAiKnowledgeBase(Ticket $ticket, SubTicketRepository $subTicketRepository): void
    {
        $resolution = $ticket->getResolution();
        if (!$resolution) {
            return;
        }

        $messages = $subTicketRepository->findByTicket($ticket->getId(), true);
        $firstMessage = $messages[0] ?? null;
        $problemMessage = $firstMessage ? $firstMessage->getMessage() : '';

        try {
            $this->ticketSupportAIService->updateKnowledgeBase(
                (string) $ticket->getSubject(),
                $problemMessage,
                $resolution,
                $ticket->getCategory()?->getName()
            );
        } catch (\Exception $e) {
            error_log('AI knowledge base sync failed: ' . $e->getMessage());
        }
    }

    private function getAdminSidebarItems(string $active): array
    {
        return [
            [
                'label' => 'Dashboard',
                'url' => $this->generateUrl('admin_dashboard'),
                'active' => $active === 'dashboard',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>',
            ],
            [
                'label' => 'Users',
                'url' => $this->generateUrl('admin_users_index'),
                'active' => $active === 'users',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>',
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
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h6m-6 4h10M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H7l-4 4v10a2 2 0 002 2z"/></svg>',
            ],
            [
                'label' => 'Ticket Categories',
                'url' => $this->generateUrl('admin_category_ticket_index'),
                'active' => $active === 'ticket_categories',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>',
            ],
            [
                'label' => 'Certificates',
                'url' => $this->generateUrl('admin_certificates_index'),
                'active' => $active === 'certificates',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
            ],
        ];
    }
}
