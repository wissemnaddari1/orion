<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\ConversationMessage;
use App\Entity\User;
use App\Repository\ConversationMessageRepository;
use App\Repository\ConversationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Messagerie: private chat between client and worker for a signed contract.
 * Tab visible for authenticated client/worker; conversation list only for fully-signed contracts.
 */
#[Route('/messagerie')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class MessagerieController extends AbstractController
{
    public function __construct(
        private ConversationRepository $conversationRepository,
        private ConversationMessageRepository $messageRepository,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Returns Twig partial to render the Messagerie tab content (conversation list + messages panel).
     */
    #[Route('', name: 'messagerie_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyUnlessClientOrWorker();

        return $this->render('messagerie/_tab_content.html.twig', [
            'csrf_message' => $this->container->get('security.csrf.token_manager')->getToken('messagerie_message')->getValue(),
            'csrf_delete' => $this->container->get('security.csrf.token_manager')->getToken('messagerie_delete')->getValue(),
        ]);
    }

    /**
     * JSON list of conversations for current user (signed contracts only; excludes soft-deleted).
     */
    #[Route('/conversations', name: 'messagerie_conversations', methods: ['GET'])]
    public function conversations(Request $request): JsonResponse
    {
        $this->denyUnlessClientOrWorker();
        /** @var User $user */
        $user = $this->getUser();

        try {
            $this->conversationRepository->ensureConversationsForUser($user);
        } catch (\Throwable $e) {
            // Don't block the UI: conversations may already exist. Log and continue.
            $this->logger->error('Messagerie ensureConversationsForUser failed', [
                'exception' => $e,
                'user_id' => $user->getId(),
            ]);
        }

        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $conversations = $this->conversationRepository->findForUser($user, $page, 20);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => 'Could not load conversations.',
            ], 500);
        }

        $list = [];
        foreach ($conversations as $conv) {
            $contract = $conv->getContract();
            $other = $conv->getOtherParticipant($user);
            $isClosed = $conv->isClosed();
            $list[] = [
                'id'            => $conv->getId(),
                'contractId'    => $contract->getId(),
                'contractTitle' => $contract->getTitle(),
                'otherName'     => $other ? trim($other->getFirstName() . ' ' . $other->getLastName()) : '',
                'lastMessageAt' => $conv->getLastMessageAt()?->format('Y-m-d H:i:s'),
                'isClosed'      => $isClosed,
                'canDelete'     => $isClosed,
                // Calls allowed when contract is fully signed (implied by conversation existence) and not closed
                'callAllowed'   => !$isClosed,
            ];
        }

        return $this->json([
            'success' => true,
            'conversations' => $list,
        ]);
    }

    /**
     * Get messages for a conversation (initial load + polling). Optional ?after=id or ?after=timestamp.
     */
    #[Route('/conversation/{id}/messages', name: 'messagerie_messages', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function messages(int $id, Request $request): JsonResponse
    {
        $this->denyUnlessClientOrWorker();
        /** @var User $user */
        $user = $this->getUser();

        $conversation = $this->conversationRepository->findOneByIdForParticipant($id, $user);
        if (!$conversation) {
            return $this->json(['success' => false, 'error' => 'Conversation not found'], 404);
        }

        $afterId = $request->query->getInt('after');
        $afterTs = $request->query->get('after_ts');
        $messages = $this->messageRepository->findByConversation($conversation, $afterId ?: null, $afterTs ?: null);

        $data = array_map(function (ConversationMessage $m) use ($user) {
            return [
                'id' => $m->getId(),
                'content' => $m->getContent(),
                'senderId' => $m->getSender()->getId(),
                'senderName' => trim($m->getSender()->getFirstName() . ' ' . $m->getSender()->getLastName()),
                'isMe' => $m->getSender()->getId() === $user->getId(),
                'createdAt' => $m->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }, $messages);

        return $this->json([
            'success' => true,
            'conversationId' => $conversation->getId(),
            'isClosed' => $conversation->isClosed(),
            'messages' => $data,
        ]);
    }

    /**
     * Send a message. Denied if contract not signed-by-both or conversation/contract closed.
     */
    #[Route('/conversation/{id}/messages', name: 'messagerie_send_message', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function sendMessage(int $id, Request $request): JsonResponse
    {
        $this->denyUnlessClientOrWorker();
        if (!$this->isCsrfTokenValid('messagerie_message', $request->request->get('_token') ?? $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['success' => false, 'error' => 'Invalid CSRF token'], 403);
        }

        /** @var User $user */
        $user = $this->getUser();
        $conversation = $this->conversationRepository->findOneByIdForParticipant($id, $user);
        if (!$conversation) {
            return $this->json(['success' => false, 'error' => 'Conversation not found'], 404);
        }

        $contract = $conversation->getContract();
        if (!$contract->isFullySigned()) {
            return $this->json(['success' => false, 'error' => 'Contract is not fully signed'], 403);
        }
        if ($contract->isClosed() || $conversation->isClosed()) {
            return $this->json(['success' => false, 'error' => 'Conversation is closed'], 403);
        }

        $content = $request->request->get('content') ?? json_decode($request->getContent(), true)['content'] ?? '';
        $content = is_string($content) ? trim($content) : '';
        if ($content === '') {
            return $this->json(['success' => false, 'error' => 'Message cannot be empty'], 400);
        }

        $msg = new ConversationMessage();
        $msg->setConversation($conversation);
        $msg->setSender($user);
        $msg->setContent($content);
        $this->em->persist($msg);
        $conversation->setLastMessageAt(new \DateTime());
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => [
                'id' => $msg->getId(),
                'content' => $msg->getContent(),
                'senderId' => $msg->getSender()->getId(),
                'senderName' => trim($msg->getSender()->getFirstName() . ' ' . $msg->getSender()->getLastName()),
                'isMe' => true,
                'createdAt' => $msg->getCreatedAt()->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * Soft-delete conversation for current user. Allowed only when contract is closed.
     * If both sides have deleted, optionally hard-delete (we keep records for safety).
     */
    #[Route('/conversation/{id}/delete', name: 'messagerie_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        $this->denyUnlessClientOrWorker();
        if (!$this->isCsrfTokenValid('messagerie_delete', $request->request->get('_token') ?? $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['success' => false, 'error' => 'Invalid CSRF token'], 403);
        }

        /** @var User $user */
        $user = $this->getUser();
        $conversation = $this->conversationRepository->findOneByIdForParticipant($id, $user);
        if (!$conversation) {
            return $this->json(['success' => false, 'error' => 'Conversation not found'], 404);
        }

        if (!$conversation->isClosed()) {
            return $this->json(['success' => false, 'error' => 'Conversation can only be deleted after the contract is closed'], 403);
        }

        $conversation->markDeletedBy($user);
        $this->em->flush();

        return $this->json(['success' => true]);
    }

    private function denyUnlessClientOrWorker(): void
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Not authenticated');
        }
        $roles = $user->getRoles();
        if (!in_array('ROLE_CLIENT', $roles, true) && !in_array('ROLE_WORKER', $roles, true)) {
            throw $this->createAccessDeniedException('Messagerie is only available for clients and workers');
        }
    }
}
