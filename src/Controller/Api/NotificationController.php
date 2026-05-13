<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\NotificationRepository;
use App\Repository\OfferRepository;
use App\Controller\BaseController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications', name: 'api_notifications_')]
#[IsGranted('ROLE_USER')]
class NotificationController extends BaseController
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private OfferRepository $offerRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * GET /notifications/dropdown
     * Returns unread_count + latest_notifications (max 10), or only unread count if only_count=1.
     * Can return HTML partial or JSON; Accept header or format param decides.
     */
    #[Route('/dropdown', name: 'dropdown', methods: ['GET'])]
    public function dropdown(Request $request): Response
    {
        $user = $this->getUser();
        if ($user === null) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $onlyCount = $request->query->getBoolean('only_count', false);

        if ($onlyCount) {
            // Keep polling path very fast: avoid JSON extraction + offer joins/subqueries.
            $unreadCount = $this->notificationRepository->countUnreadByUser($user);
            return new JsonResponse([
                'unread_count' => $unreadCount,
            ]);
        }

        // Full dropdown can afford richer filtering.
        $unreadCount = $this->notificationRepository->countUnreadOfferOnlyByUser($user);

        $latest = $this->notificationRepository->findLatestOfferOnlyForUser($user, 10);
        $data = [
            'unread_count' => $unreadCount,
            'latest_notifications' => array_map(function ($n) {
                return [
                    'id' => $n->getId(),
                    'title' => $n->getTitle(),
                    'body' => $n->getBody(),
                    'createdAt' => $n->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                    'isRead' => $n->isRead(),
                    'type' => $n->getType(),
                    'payload' => $n->getPayload(),
                ];
            }, $latest),
        ];

        if ($request->query->get('format') === 'html') {
            $notificationRows = $this->buildNotificationRows($latest, $user->getId());
            return $this->render('components/_notification_dropdown_content.html.twig', [
                'unread_count' => $data['unread_count'],
                'notifications' => $latest,
                'notification_rows' => $notificationRows,
            ]);
        }

        return new JsonResponse($data);
    }

    /**
     * POST /notifications/{id}/read — mark as read (ownership checked).
     */
    #[Route('/{id}/read', name: 'read', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function markRead(int $id): JsonResponse
    {
        $user = $this->getUser();
        if ($user === null) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $notification = $this->notificationRepository->findOneByIdAndUser($id, $user);
        if ($notification === null) {
            return new JsonResponse(['error' => 'Notification not found'], Response::HTTP_NOT_FOUND);
        }

        $notification->setIsRead(true);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'id' => $notification->getId(),
        ]);
    }

    /**
     * @param list<\App\Entity\Notification> $notifications
     * @return list<array{notification: \App\Entity\Notification, is_client_offer: bool}>
     */
    private function buildNotificationRows(array $notifications, int $userId): array
    {
        $offerIds = [];
        foreach ($notifications as $n) {
            $payload = $n->getPayload();
            if (isset($payload['offer_id'])) {
                $offerIds[] = (int) $payload['offer_id'];
            }
        }
        $offerIds = array_values(array_unique(array_filter($offerIds)));
        $offers = $offerIds === [] ? [] : $this->offerRepository->findBy(['id' => $offerIds]);
        $offerById = [];
        foreach ($offers as $o) {
            $offerById[$o->getId()] = $o;
        }
        $rows = [];
        foreach ($notifications as $n) {
            $payload = $n->getPayload();
            $offerId = isset($payload['offer_id']) ? (int) $payload['offer_id'] : null;
            $offer = $offerId !== null ? ($offerById[$offerId] ?? null) : null;
            $isClientOffer = $offer !== null && $offer->getClient() !== null && $offer->getClient()->getId() === $userId;
            $rows[] = ['notification' => $n, 'is_client_offer' => $isClientOffer];
        }
        return $rows;
    }
}
