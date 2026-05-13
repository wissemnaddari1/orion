<?php

namespace App\Controller;

use App\Entity\Ticket;
use App\Entity\SubTicket;
use App\Form\TicketType;
use App\Form\SubTicketType;
use App\Form\SatisfactionRatingType;
use App\Repository\CategoryTicketRepository;
use App\Repository\TicketRepository;
use App\Repository\SubTicketRepository;
use App\Repository\WorkerCategoryRepository;
use App\Service\TicketAiInsightService;
use App\Service\MessageModerationService;
use App\Enum\UserRole;
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
 * Ticket Controller for Client and Worker roles
 * Implements strict access control and server-side validation
 */
#[Route('/ticket')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class TicketController extends AbstractController
{
    public function __construct(
        private WorkerCategoryRepository $categoryRepository,
        private TicketAiInsightService $ticketAiInsightService,
        private \App\Service\TicketSupportAIService $ticketSupportAIService,
        private MessageModerationService $messageModerationService,
    )
    {
    }
    /**
     * List all tickets for the current user (AJAX endpoint for popup)
     */
    #[Route('/list', name: 'ticket_list', methods: ['GET'])]
    public function list(TicketRepository $ticketRepository, SubTicketRepository $subTicketRepository): JsonResponse
    {
        $user = $this->getUser();
        
        // Get all tickets created by this user
        $tickets = $ticketRepository->findByUser($user->getId());
        
        // Batch-load unread counts in a single query (avoids N+1)
        $ticketIds = array_map(fn(Ticket $t) => $t->getId(), $tickets);
        $unreadMap = $subTicketRepository->countUnreadForUserByTickets($ticketIds, $user->getId());

        // Transform to array for JSON response
        $data = array_map(function(Ticket $ticket) use ($unreadMap) {
            return [
                'id' => $ticket->getId(),
                'subject' => $ticket->getSubject(),
                'status' => $ticket->getStatus(),
                'priority' => $ticket->getPriority(),
                'category' => $ticket->getCategory()->getName(),
                'messageCount' => $ticket->getMessageCount(),
                'unreadCount' => $unreadMap[$ticket->getId()] ?? 0,
                'lastMessageAt' => $ticket->getLastMessageAt()?->format('Y-m-d H:i'),
                'createdAt' => $ticket->getCreatedAt()->format('Y-m-d H:i'),
            ];
        }, $tickets);
        
        // Calculate total unread count
        $totalUnread = array_sum(array_column($data, 'unreadCount'));
        
        return $this->json([
            'success' => true,
            'tickets' => $data,
            'count' => count($data),
            'totalUnread' => $totalUnread
        ]);
    }

    /**
     * Get messages for a ticket (AJAX endpoint for messenger popup)
     */
    #[Route('/{id}/messages', name: 'ticket_messages', methods: ['GET'])]
    public function messages(
        int $id,
        TicketRepository $ticketRepository,
        SubTicketRepository $subTicketRepository
    ): JsonResponse {
        $user = $this->getUser();
        
        // Verify ownership
        $ticket = $ticketRepository->findOneByIdAndUser($id, $user->getId());
        
        if (!$ticket) {
            return $this->json([
                'success' => false,
                'error' => 'Ticket not found'
            ], 404);
        }
        
        // Get all messages (excluding internal admin notes)
        $messages = $subTicketRepository->findByTicket($id, false);
        
        // Mark messages as read (excluding user's own messages)
        $subTicketRepository->markAsReadByTicket($id, $user->getId());
        
        // Transform messages to array
        $messagesData = array_map(function(SubTicket $message) {
            return [
                'id' => $message->getId(),
                'message' => $message->getMessage(),
                'senderName' => $message->getSender()->getFirstName() . ' ' . $message->getSender()->getLastName(),
                'senderRole' => $message->getSenderRole(),
                'createdAt' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
                'isEdited' => $message->isEdited(),
                'fileName' => $message->getFileName(),
                'filePath' => $message->getFilePath(),
                'fileSize' => $message->getFileSize(),
            ];
        }, $messages);
        
        return $this->json([
            'success' => true,
            'ticket' => [
                'id' => $ticket->getId(),
                'subject' => $ticket->getSubject(),
                'status' => $ticket->getStatus(),
                'priority' => $ticket->getPriority(),
                'category' => $ticket->getCategory()->getName(),
            ],
            'messages' => $messagesData
        ]);
    }

    /**
     * Quick reply via AJAX (for messenger popup)
     */
    #[Route('/{id}/quick-reply', name: 'ticket_quick_reply', methods: ['POST'])]
    public function quickReply(
        int $id,
        Request $request,
        TicketRepository $ticketRepository,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): JsonResponse {
        $user = $this->getUser();
        
        // Verify ownership
        $ticket = $ticketRepository->findOneByIdAndUser($id, $user->getId());
        
        if (!$ticket) {
            return $this->json([
                'success' => false,
                'error' => 'Ticket not found'
            ], 404);
        }
        
        // Check if ticket is closed
        if ($ticket->getStatus() === 'CLOSED') {
            return $this->json([
                'success' => false,
                'error' => 'Cannot reply to a closed ticket'
            ], 400);
        }
        
        // Check if request has file upload (multipart/form-data)
        $message = '';
        $hasFile = $request->files->count() > 0;
        
        if ($hasFile) {
            // FormData request with possible file
            $message = $request->request->get('message', '');
        } else {
            // JSON request
            $data = json_decode($request->getContent(), true);
            $message = $data['message'] ?? '';
        }
        
        if (empty(trim($message))) {
            return $this->json([
                'success' => false,
                'error' => 'Message cannot be empty'
            ], 400);
        }

        // Moderation check  block toxic messages
        $moderation = $this->messageModerationService->moderateMessage($message);
        if ($moderation['is_toxic']) {
            return $this->json([
                'success' => false,
                'error' => 'Your message contains inappropriate content and cannot be sent. Please revise your message.'
            ], 400);
        }
        

        // Moderation check ÔÇö block toxic messages
        $moderation = $this->messageModerationService->moderateMessage($message);
        if ($moderation['is_toxic']) {
            return $this->json([
                'success' => false,
                'error' => 'Your message contains inappropriate content and cannot be sent. Please revise your message.'
            ], 400);
        }

        // Create reply
        $subTicket = new SubTicket();
        $subTicket->setTicket($ticket);
        $subTicket->setSender($user);
        $subTicket->setMessage($message);
        $subTicket->setSenderRole($user->getRole()->value);
        $subTicket->setIsInternal(false);
        $subTicket->setIsRead(false);
        $subTicket->setIsEdited(false);
        $subTicket->setIsDeleted(false);
        
        // Handle file attachment if provided
        $attachmentFile = $request->files->get('attachment');
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
                return $this->json([
                    'success' => false,
                    'error' => 'File upload failed: ' . $e->getMessage()
                ], 500);
            }
        }
        
        // Update ticket
        $ticket->setLastMessageAt(new \DateTime());
        $ticket->setMessageCount($ticket->getMessageCount() + 1);
        
        // Re-open ticket if it was in a waiting state
        if ($ticket->getStatus() === 'WAITING_USER') {
            $ticket->setStatus('IN_PROGRESS');
        }
        
        $entityManager->persist($subTicket);
        $entityManager->flush();
        
        return $this->json([
            'success' => true,
            'message' => [
                'id' => $subTicket->getId(),
                'message' => $subTicket->getMessage(),
                'senderName' => $user->getFirstName() . ' ' . $user->getLastName(),
                'senderRole' => $subTicket->getSenderRole(),
                'createdAt' => $subTicket->getCreatedAt()->format('Y-m-d H:i:s'),
                'fileName' => $subTicket->getFileName(),
                'filePath' => $subTicket->getFilePath(),
                'fileSize' => $subTicket->getFileSize(),
            ]
        ]);
    }

    /**
     * Get categories for ticket creation (AJAX endpoint)
     */
    #[Route('/categories/list', name: 'ticket_categories', methods: ['GET'])]
    public function categories(CategoryTicketRepository $categoryRepository): JsonResponse
    {
        $categories = $categoryRepository->findAllOrdered();
        
        $data = array_map(function($category) {
            return [
                'id' => $category->getId(),
                'name' => $category->getName(),
            ];
        }, $categories);
        
        return $this->json([
            'success' => true,
            'categories' => $data,
            'csrf_token' => $this->generateCsrfToken('ticket_item')
        ]);
    }
    

    /**
     * Parse a free-form paragraph into structured ticket fields using AI
     */
    #[Route('/parse-paragraph', name: 'ticket_parse_paragraph', methods: ['POST'])]
    public function parseParagraph(
        Request $request,
        CategoryTicketRepository $categoryRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $paragraph = trim($data['paragraph'] ?? '');

        if ('' === $paragraph) {
            return $this->json(['success' => false, 'error' => 'Please enter a description.'], 400);
        }

        if (strlen($paragraph) < 10) {
            return $this->json(['success' => false, 'error' => 'Please provide a more detailed description (at least 10 characters).'], 400);
        }

        // Get available categories (capped at 100)
        $categories = $categoryRepository->findAllOrdered();
        $categoryData = array_map(function ($cat) {
            return ['id' => $cat->getId(), 'name' => $cat->getName()];
        }, $categories);

        // Call AI to parse the paragraph
        $result = $this->ticketAiInsightService->parseTicketFromParagraph($paragraph, $categoryData);

        if (null === $result) {
            return $this->json([
                'success' => false,
                'error' => 'AI could not process your description. Please try again or fill the form manually.',
            ], 500);
        }

        return $this->json([
            'success' => true,
            'subject' => $result['subject'],
            'category_id' => $result['category_id'],
            'priority' => $result['priority'],
            'message' => $result['message'],
        ]);
    }

    private function generateCsrfToken(string $tokenId): string
    {
        return $this->container->get('security.csrf.token_manager')->getToken($tokenId)->getValue();
    }

    /**
     * Handle AI solution suggestion for a ticket
     * 
     * @param Ticket $ticket The ticket to get a solution for
     * @param SubTicket $firstMessage The first message of the ticket
     * @param EntityManagerInterface $entityManager The entity manager
     * @return bool True if AI provided a solution, false otherwise
     */
    private function handleAiSolution(
        Ticket $ticket, 
        SubTicket $firstMessage, 
        EntityManagerInterface $entityManager
    ): bool {
        try {
            // Get AI solution suggestion
            $categoryName = $ticket->getCategory() ? $ticket->getCategory()->getName() : null;
            $aiSolution = $this->ticketSupportAIService->solveTicket(
                (string) $ticket->getSubject(),
                (string) $firstMessage->getMessage(),
                $categoryName
            );

            // If AI is confident, auto-respond with the solution
            if (!$aiSolution['escalate_to_admin'] && $aiSolution['confidence_score'] >= 0.75 && $aiSolution['suggested_solution']) {
                // Use admin user for AI responses (required for SubTicket sender)
                $adminUser = $entityManager->getRepository(\App\Entity\User::class)
                    ->findOneBy(['role' => UserRole::ADMIN->value]);
                if (!$adminUser) {
                    $adminUser = $entityManager->getRepository(\App\Entity\User::class)
                        ->findOneBy([], ['id' => 'ASC']);
                }
                if (!$adminUser) {
                    return false;
                }

                // Create an AI response as a sub-ticket
                $aiResponse = new SubTicket();
                $aiResponse->setTicket($ticket);
                $aiResponse->setSender($adminUser);
                $aiResponse->setMessage($aiSolution['suggested_solution']);
                $aiResponse->setSenderRole('ADMIN');
                $aiResponse->setIsInternal(false);
                $aiResponse->setIsRead(false);
                $aiResponse->setIsEdited(false);
                $aiResponse->setIsDeleted(false);
                // created_at comes from TimestampableTrait (constructor + PrePersist); no setCreatedAt().

                // Add AI response as a message only - do NOT close the ticket
                // Only admin can close tickets; user can reply if solution doesn't work
                $ticket->setMessageCount($ticket->getMessageCount() + 1);
                $ticket->setLastMessageAt(new \DateTime());
                
                $entityManager->persist($aiResponse);
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            // Log the error but don't fail the ticket creation
            error_log('AI solution error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create new ticket
     */
    #[Route('/create', name: 'ticket_create', methods: ['GET', 'POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $isAjax = $request->isXmlHttpRequest();

        try {
            $user = $this->getUser();
            if (!$user) {
                if ($isAjax) {
                    return $this->json(['success' => false, 'message' => 'Session expired. Please log in again.'], 401);
                }
                return $this->redirectToRoute('app_login');
            }

            $ticket = new Ticket();
            $form = $this->createForm(TicketType::class, $ticket);
            $form->handleRequest($request);

            // SERVER-SIDE VALIDATION (contr├┤le de saisie)
            if ($form->isSubmitted() && $form->isValid()) {
                try {
                // Set ticket properties
                $ticket->setStatus('OPEN');
                $ticket->setMessageCount(1);
                $ticket->setLastMessageAt(new \DateTime());
                $ticket->setAcknowledgedByAd(false);
                
                // Create first SubTicket (initial message)
                $initialMessage = $form->get('message')->getData();

                // Moderation check ÔÇö block toxic messages
                $moderation = $this->messageModerationService->moderateMessage($initialMessage);
                if ($moderation['is_toxic']) {
                    if ($isAjax) {
                        return $this->json([
                            'success' => false,
                            'message' => 'Your message contains inappropriate content and cannot be sent. Please revise your message.'
                        ], 400);
                    }
                    $this->addFlash('error', 'Your message contains inappropriate content and cannot be sent. Please revise your message.');
                    return $this->redirectToRoute('ticket_create');
                }

                $subTicket = new SubTicket();
                $subTicket->setTicket($ticket);
                $subTicket->setSender($user);
                $subTicket->setMessage($initialMessage);
                $subTicket->setSenderRole($user->getRole()->value);
                $subTicket->setIsInternal(false);
                $subTicket->setIsRead(false);
                $subTicket->setIsEdited(false);
                $subTicket->setIsDeleted(false);
                
                // Handle file attachment if provided
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
                        
                        // Ensure directory exists with proper permissions
                        if (!is_dir($uploadsDirectory)) {
                            mkdir($uploadsDirectory, 0777, true);
                        }

                        // Move file immediately after getting metadata
                        $attachmentFile->move($uploadsDirectory, $newFilename);
                        
                        $subTicket->setFileName($originalFilename.'.'.$extension);
                        $subTicket->setFilePath('/uploads/tickets/'.$newFilename);
                        $subTicket->setFileType($clientMimeType);
                        $subTicket->setFileSize($clientSize);
                    } catch (\Exception $e) {
                        throw new \Exception('File upload failed: ' . $e->getMessage());
                    }
                }

                $aiInsight = $this->ticketAiInsightService->analyzeTicket(
                    (string) $ticket->getSubject(),
                    (string) $subTicket->getMessage(),
                );

                if (null !== $aiInsight) {
                    $ticket->setAiSentiment(isset($aiInsight['sentiment']) ? (string) $aiInsight['sentiment'] : null);
                    $ticket->setAiUrgency(isset($aiInsight['urgency']) ? (string) $aiInsight['urgency'] : null);
                    $ticket->setAiSuggestedPriority(isset($aiInsight['suggested_priority']) ? (string) $aiInsight['suggested_priority'] : null);
                    $ticket->setAiSummary(isset($aiInsight['short_summary']) ? (string) $aiInsight['short_summary'] : null);
                }
                
                // Handle AI solution suggestion
                $this->handleAiSolution($ticket, $subTicket, $entityManager);

                $entityManager->persist($ticket);
                $entityManager->persist($subTicket);
                $entityManager->flush();

                $this->addFlash('success', 'Your support ticket has been created successfully!');
                
                // Return JSON for AJAX, or redirect for normal form submission
                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'success' => true,
                        'ticketId' => $ticket->getId(),
                        'subject' => $ticket->getSubject(),
                        'message' => 'Ticket created successfully!'
                    ]);
                }
                
                return $this->redirectToRoute('ticket_view', ['id' => $ticket->getId()]);
            } catch (\Throwable $e) {
                // Catch TypeError / Error too (inner block used to only catch Exception, which hid the real error behind a generic 500).
                error_log('Ticket creation error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

                if ($isAjax) {
                    $detail = $this->getParameter('kernel.debug')
                        ? ('Failed to create ticket: ' . $e->getMessage())
                        : 'Failed to create ticket. Please try again.';

                    return $this->json([
                        'success' => false,
                        'message' => $detail,
                    ], 500);
                }

                $this->addFlash('error', 'Failed to create ticket. Please try again.');
            }
        } else if ($form->isSubmitted()) {
            // Form has validation errors
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }

            if ($isAjax) {
                return $this->json([
                    'success' => false,
                    'message' => 'Validation errors: ' . implode(', ', $errors),
                    'errors' => $errors
                ], 400);
            }
        } else if ($isAjax && $request->isMethod('POST')) {
            // AJAX POST but form not submitted (e.g. CSRF invalid, wrong content-type, or form name mismatch)
            return $this->json([
                'success' => false,
                'message' => 'Invalid request. Please refresh the page and try again.'
            ], 400);
        }

        return $this->render('ticket/create.html.twig', [
            'form' => $form->createView(),
            'sidebar_items' => $this->getSidebarItems(),
            'topbar_title' => 'Create Ticket',
        ]);
        } catch (\Throwable $e) {
            error_log('Ticket creation unexpected error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            if ($isAjax) {
                return $this->json([
                    'success' => false,
                    'message' => 'Failed to create ticket. Please try again.'
                ], 500);
            }
            $this->addFlash('error', 'Failed to create ticket. Please try again.');
            return $this->redirectToRoute('ticket_create');
        }
    }

    /**
     * View ticket details with all messages
     * Security: Only the ticket owner can view it
     */
    #[Route('/{id}', name: 'ticket_view', methods: ['GET'])]
    public function view(
        int $id,
        Request $request,
        TicketRepository $ticketRepository,
        SubTicketRepository $subTicketRepository
    ): Response {
        $user = $this->getUser();
        
        // Fetch ticket and verify ownership
        $ticket = $ticketRepository->findOneByIdAndUser($id, $user->getId());
        
        if (!$ticket) {
            throw $this->createAccessDeniedException('You do not have access to this ticket.');
        }
        
        // Get all messages (excluding internal admin notes)
        $messages = $subTicketRepository->findByTicket($id, false);
        
        // Mark unread messages as read
        $subTicketRepository->markAsReadByTicket($id, $user->getId());
        
        // Create reply form
        $replyForm = $this->createForm(SubTicketType::class, null, [
            'is_admin' => false
        ]);
        
        // Create satisfaction form if ticket is closed and not yet rated
        $satisfactionForm = null;
        $showRatingModal = false;
        if ($ticket->getStatus() === 'CLOSED' && !$ticket->getSatisfactionRating()) {
            $satisfactionForm = $this->createForm(SatisfactionRatingType::class);
            
            // Check if user hasn't been reminded yet (no cookie set)
            $cookieName = 'rating_reminded_' . $ticket->getId();
            $showRatingModal = !$request->cookies->has($cookieName);
        }
        
        return $this->render('ticket/view.html.twig', [
            'ticket' => $ticket,
            'messages' => $messages,
            'replyForm' => $replyForm->createView(),
            'satisfactionForm' => $satisfactionForm?->createView(),
            'showRatingModal' => $showRatingModal,
            'sidebar_items' => $this->getSidebarItems(),
            'topbar_title' => 'Ticket Details',
        ]);
    }

    /**
     * Reply to a ticket
     * Security: Only the ticket owner can reply
     */
    #[Route('/{id}/reply', name: 'ticket_reply', methods: ['POST'])]
    public function reply(
        int $id,
        Request $request,
        TicketRepository $ticketRepository,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $user = $this->getUser();
        
        // Verify ownership
        $ticket = $ticketRepository->findOneByIdAndUser($id, $user->getId());
        
        if (!$ticket) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => false, 'error' => 'Ticket not found'], 404);
            }
            throw $this->createAccessDeniedException('You do not have access to this ticket.');
        }
        
        // Prevent replies to closed tickets
        if ($ticket->getStatus() === 'CLOSED') {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => false, 'error' => 'Cannot reply to a closed ticket'], 400);
            }
            $this->addFlash('error', 'Cannot reply to a closed ticket.');
            return $this->redirectToRoute('ticket_view', ['id' => $id]);
        }
        
        // Handle AJAX request (popup) - direct validation without form component
        if ($request->isXmlHttpRequest()) {
            // Get form data
            $submittedData = $request->request->all()['sub_ticket_type'] ?? [];
            $submittedToken = $submittedData['_token'] ?? '';
            $message = $submittedData['message'] ?? '';
            
            // Validate CSRF token
            if (!$this->isCsrfTokenValid('subticket_item', $submittedToken)) {
                return $this->json(['success' => false, 'error' => 'Invalid CSRF token'], 400);
            }
            
            // Validate message
            if (empty(trim($message))) {
                return $this->json(['success' => false, 'error' => 'Please enter a reply message.'], 400);
            }
            
            if (strlen($message) < 3) {
                return $this->json(['success' => false, 'error' => 'Reply must be at least 3 characters.'], 400);
            }
            
            if (strlen($message) > 5000) {
                return $this->json(['success' => false, 'error' => 'Reply cannot be longer than 5000 characters.'], 400);
            }
            

            // Moderation check ÔÇö block toxic messages
            $moderation = $this->messageModerationService->moderateMessage($message);
            if ($moderation['is_toxic']) {
                return $this->json(['success' => false, 'error' => 'Your message contains inappropriate content and cannot be sent. Please revise your message.'], 400);
            }

            // Create SubTicket
            $subTicket = new SubTicket();
            $subTicket->setTicket($ticket);
            $subTicket->setSender($user);
            $subTicket->setMessage($message);
            $subTicket->setSenderRole($user->getRole()->value);
            $subTicket->setIsInternal(false);
            $subTicket->setIsRead(false);
            $subTicket->setIsEdited(false);
            $subTicket->setIsDeleted(false);
            
            // Handle file attachment if present
            $filesData = $request->files->all()['sub_ticket_type'] ?? [];
            $attachmentFile = $filesData['attachment'] ?? null;
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
                    return $this->json(['success' => false, 'error' => 'File upload failed: ' . $e->getMessage()], 400);
                }
            }
            
            // Update ticket
            $ticket->setLastMessageAt(new \DateTime());
            $ticket->setMessageCount($ticket->getMessageCount() + 1);
            
            if ($ticket->getStatus() === 'WAITING_USER') {
                $ticket->setStatus('IN_PROGRESS');
            }
            
            $entityManager->persist($subTicket);
            $entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Reply sent successfully',
                'data' => [
                    'id' => $subTicket->getId(),
                    'message' => $subTicket->getMessage(),
                    'sender' => $user->getFirstName() . ' ' . $user->getLastName(),
                    'senderRole' => $subTicket->getSenderRole(),
                    'createdAt' => $subTicket->getCreatedAt()->format('Y-m-d H:i:s'),
                    'fileName' => $subTicket->getFileName(),
                    'filePath' => $subTicket->getFilePath()
                ]
            ]);
        }
        
        // Non-AJAX request - traditional form handling
        $form = $this->createForm(SubTicketType::class, null, [
            'is_admin' => false
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $messageText = $form->get('message')->getData();

            // Moderation check ÔÇö block toxic messages
            $moderation = $this->messageModerationService->moderateMessage($messageText);
            if ($moderation['is_toxic']) {
                if ($request->isXmlHttpRequest()) {
                    return $this->json(['success' => false, 'error' => 'Your message contains inappropriate content and cannot be sent. Please revise your message.'], 400);
                }
                $this->addFlash('error', 'Your message contains inappropriate content and cannot be sent. Please revise your message.');
                return $this->redirectToRoute('ticket_view', ['id' => $id]);
            }

            $subTicket = new SubTicket();
            $subTicket->setTicket($ticket);
            $subTicket->setSender($user);
            $subTicket->setMessage($messageText);
            $subTicket->setSenderRole($user->getRole()->value);
            $subTicket->setIsInternal(false);
            $subTicket->setIsRead(false);
            $subTicket->setIsEdited(false);
            $subTicket->setIsDeleted(false);
            
            $attachmentFile = $form->get('attachment')->getData();
            if ($attachmentFile) {
                try {
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
                    
                    if (!is_dir($uploadsDirectory)) {
                        mkdir($uploadsDirectory, 0777, true);
                    }

                    $attachmentFile->move($uploadsDirectory, $newFilename);
                    
                    $subTicket->setFileName($originalFilename.'.'.$extension);
                    $subTicket->setFilePath('/uploads/tickets/'.$newFilename);
                    $subTicket->setFileType($clientMimeType);
                    $subTicket->setFileSize($clientSize);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'File upload failed: ' . $e->getMessage());
                }
            }
            
            $ticket->setLastMessageAt(new \DateTime());
            $ticket->setMessageCount($ticket->getMessageCount() + 1);
            
            if ($ticket->getStatus() === 'WAITING_USER') {
                $ticket->setStatus('IN_PROGRESS');
            }
            
            $entityManager->persist($subTicket);
            $entityManager->flush();

            $this->addFlash('success', 'Your reply has been sent.');
            
            return $this->redirectToRoute('ticket_view', ['id' => $id]);
        }
        
        // If validation failed, redirect back with errors
        if ($form->isSubmitted()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }
            if (!empty($errors)) {
                $this->addFlash('error', implode(' ', $errors));
            } else {
                $this->addFlash('error', 'Your message could not be sent. Please ensure it contains at least 3 characters.');
            }
        }
        return $this->redirectToRoute('ticket_view', ['id' => $id]);
    }

    /**
     * Submit satisfaction rating after ticket closure
     */
    #[Route('/{id}/satisfaction', name: 'ticket_satisfaction', methods: ['POST'])]
    public function satisfaction(
        int $id,
        Request $request,
        TicketRepository $ticketRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();
        
        // Verify ownership
        $ticket = $ticketRepository->findOneByIdAndUser($id, $user->getId());
        
        if (!$ticket) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => false, 'error' => 'Ticket not found'], 404);
            }
            throw $this->createAccessDeniedException();
        }
        
        // Only allow rating if ticket is closed and not already rated
        if ($ticket->getStatus() !== 'CLOSED') {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => false, 'error' => 'Can only rate closed tickets'], 400);
            }
            $this->addFlash('error', 'Can only rate closed tickets.');
            return $this->redirectToRoute('ticket_view', ['id' => $id]);
        }
        
        if ($ticket->getSatisfactionRating()) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => false, 'error' => 'You have already rated this ticket'], 400);
            }
            $this->addFlash('error', 'You have already rated this ticket.');
            return $this->redirectToRoute('ticket_view', ['id' => $id]);
        }
        
        // Handle JSON request
        if ($request->isXmlHttpRequest() && $request->getContent()) {
            $data = json_decode($request->getContent(), true);
            
            // Validate CSRF token
            if (!isset($data['_token']) || !$this->isCsrfTokenValid('satisfaction_rating', $data['_token'])) {
                return $this->json(['success' => false, 'error' => 'Invalid CSRF token'], 400);
            }
            
            // Validate rating
            if (!isset($data['rating']) || $data['rating'] < 1 || $data['rating'] > 5) {
                return $this->json(['success' => false, 'error' => 'Please select a rating between 1 and 5 stars'], 400);
            }
            
            // Save rating
            $ticket->setSatisfactionRating((int)$data['rating']);
            $ticket->setSatisfactionComment($data['comment'] ?? null);
            $entityManager->flush();
            
            return $this->json([
                'success' => true,
                'message' => 'Thank you for your feedback!',
                'rating' => (int)$data['rating'],
                'comment' => $data['comment'] ?? null
            ]);
        }
        
        // Fallback to form handling for non-AJAX requests
        $form = $this->createForm(SatisfactionRatingType::class);
        $form->handleRequest($request);

        // SERVER-SIDE VALIDATION
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            
            error_log('Form is valid! Data: ' . json_encode($data));
            
            $ticket->setSatisfactionRating($data['satisfactionRating']);
            $ticket->setSatisfactionComment($data['satisfactionComment'] ?? null);
            
            $entityManager->flush();

            // Return JSON for AJAX requests (modal popup)
            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'success' => true,
                    'message' => 'Thank you for your feedback!',
                    'rating' => $data['satisfactionRating'],
                    'comment' => $data['satisfactionComment'] ?? null
                ]);
            }

            $this->addFlash('success', 'Thank you for your feedback!');
            
            return $this->redirectToRoute('ticket_view', ['id' => $id]);
        }
        
        // If form was submitted but invalid, log errors
        if ($form->isSubmitted()) {
            error_log('Form submitted but invalid');
            foreach ($form->getErrors(true) as $error) {
                error_log('Form error: ' . $error->getMessage());
            }
        }
        
        // If validation failed
        if ($request->isXmlHttpRequest()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }
            $errorMsg = !empty($errors) ? implode(', ', $errors) : 'Invalid form submission';
            error_log('Returning error: ' . $errorMsg);
            return $this->json(['success' => false, 'error' => $errorMsg], 400);
        }
        
        return $this->redirectToRoute('ticket_view', ['id' => $id]);
    }

    /**
     * Get sidebar items based on user role
     */
    private function getSidebarItems(): array
    {
        $user = $this->getUser();
        $role = $user->getRole()->value;

        if ($role === 'WORKER') {
            return $this->getWorkerSidebarItems();
        }

        // Default: CLIENT sidebar
        return $this->getClientSidebarItems();
    }

    private function getClientSidebarItems(): array
    {
        return [
            [
                'label' => 'Dashboard',
                'url' => $this->generateUrl('client_dashboard'),
                'active' => false,
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>',
            ],
            [
                'label' => 'My Requests',
                'url' => '#',
                'active' => false,
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>',
            ],
            [
                'label' => 'Apply as Freelancer',
                'url' => $this->generateUrl('client_apply_freelancer'),
                'active' => false,
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>',
            ],
        ];
    }

    private function getWorkerSidebarItems(): array
    {
        return [
            [
                'label' => 'Dashboard',
                'url' => $this->generateUrl('worker_dashboard'),
                'active' => false,
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>',
            ],
            [
                'label' => 'My Offers',
                'url' => '#',
                'active' => false,
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>',
            ],
            [
                'label' => 'Contracts',
                'url' => '#',
                'active' => false,
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
            ],
        ];
    }
}
