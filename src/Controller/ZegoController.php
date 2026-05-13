<?php

namespace App\Controller;

use App\Entity\Contract;
use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Service\ZegoTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Handles ZEGOCloud voice/video calls integrated into the Messagerie.
 *
 * Routes:
 *   GET /zego/token/{conversationId}       — Backend token generation (JSON).
 *   GET /messagerie/call/{conversationId}  — Full-page call UI (Twig).
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ZegoController extends AbstractController
{
    public function __construct(
        private readonly ConversationRepository $conversationRepository,
        private readonly ZegoTokenService       $zegoTokenService,
    ) {}

    /**
     * Returns a ZEGO Token04 for the given conversation's room.
     *
     * Security:
     *   - Current user must be a participant (client or worker).
     *   - Contract must be fully signed (both sides).
     *   - Contract must NOT be closed (COMPLETED / CANCELLED / DISPUTED).
     *
     * Response JSON:
     *   { appId, token, userId, userName, roomId }
     */
    #[Route('/zego/token/{conversationId}', name: 'zego_token', methods: ['GET'], requirements: ['conversationId' => '\d+'])]
    public function token(int $conversationId): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->denyUnlessClientOrWorker($user);

        $conversation = $this->conversationRepository->find($conversationId);

        if ($conversation === null || !$conversation->isParticipant($user)) {
            return $this->json(['error' => 'Conversation not found or access denied.'], 404);
        }

        $contract = $conversation->getContract();

        if (!$contract->isFullySigned()) {
            return $this->json(['error' => 'Calls are only available for fully signed contracts.'], 403);
        }

        if ($contract->isClosed()) {
            return $this->json(['error' => 'Calls are not available for closed contracts.'], 403);
        }

        $zegoUserId = (string) $user->getId();
        $userName   = trim($user->getFirstName() . ' ' . $user->getLastName()) ?: $user->getEmail();
        $roomId     = ZegoTokenService::roomIdForContract($contract->getId());

        try {
            $token = $this->zegoTokenService->generateToken($zegoUserId, $roomId);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Token generation failed: ' . $e->getMessage()], 500);
        }

        return $this->json([
            'appId'    => $this->zegoTokenService->getAppId(),
            'token'    => $token,
            'userId'   => $zegoUserId,
            'userName' => $userName,
            'roomId'   => $roomId,
        ]);
    }

    /**
     * Renders the dedicated call page.
     *
     * Query params:
     *   ?type=voice  (default)
     *   ?type=video
     *
     * Security: same as token endpoint.
     */
    #[Route('/messagerie/call/{conversationId}', name: 'messagerie_call', methods: ['GET'], requirements: ['conversationId' => '\d+'])]
    public function call(int $conversationId, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->denyUnlessClientOrWorker($user);

        $conversation = $this->conversationRepository->find($conversationId);

        if ($conversation === null || !$conversation->isParticipant($user)) {
            throw $this->createNotFoundException('Conversation not found or access denied.');
        }

        $contract = $conversation->getContract();

        if (!$contract->isFullySigned()) {
            throw $this->createAccessDeniedException('Calls are only available for fully signed contracts.');
        }

        if ($contract->isClosed()) {
            throw $this->createAccessDeniedException('Calls are not available for closed contracts.');
        }

        $type = $request->query->get('type', 'voice');
        if (!in_array($type, ['voice', 'video'], true)) {
            $type = 'voice';
        }

        $other = $conversation->getOtherParticipant($user);

        return $this->render('messagerie/call.html.twig', [
            'conversationId'  => $conversationId,
            'contractTitle'   => $contract->getTitle(),
            'otherName'       => $other ? trim($other->getFirstName() . ' ' . $other->getLastName()) : 'Other party',
            'callType'        => $type,
            'tokenUrl'        => $this->generateUrl('zego_token', ['conversationId' => $conversationId]),
            'backUrl'         => $this->generateUrl('messagerie_index'),
        ]);
    }

    private function denyUnlessClientOrWorker(User $user): void
    {
        $roles = $user->getRoles();
        if (!in_array('ROLE_CLIENT', $roles, true) && !in_array('ROLE_WORKER', $roles, true)) {
            throw $this->createAccessDeniedException('Calls are only available for clients and workers.');
        }
    }
}
