<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Offer;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;

final class NotificationService
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function notifyMatchFoundForFreelancer(User $freelancer, int $requestId, int $offerId, int $clientId, ?float $score = null): Notification
    {
        $n = new Notification();
        $n->setUser($freelancer);
        $n->setType(Notification::TYPE_OFFER_RECEIVED);
        $n->setTitle('New request match');
        $n->setBody('A client\'s service request matches your profile. Review and respond.');
        $n->setPayload([
            'request_id' => $requestId,
            'offer_id' => $offerId,
            'client_id' => $clientId,
            'score' => $score,
        ]);
        $this->entityManager->persist($n);
        return $n;
    }

    public function notifyMatchFoundForClient(User $client, int $requestId, int $count): Notification
    {
        $n = new Notification();
        $n->setUser($client);
        $n->setType(Notification::TYPE_MATCH_FOUND);
        $n->setTitle('Top freelancers ranked');
        $n->setBody(sprintf('Top %d freelancers ranked for your request. Click to review.', $count));
        $n->setPayload(['request_id' => $requestId]);
        $this->entityManager->persist($n);
        return $n;
    }

    /** One notification per offer for the client so they can Accept/Decline/Negotiate from the dropdown. */
    public function notifyClientOfferMatch(User $client, int $requestId, int $offerId, string $freelancerName, ?float $score): Notification
    {
        $n = new Notification();
        $n->setUser($client);
        $n->setType(Notification::TYPE_MATCH_FOUND);
        $n->setTitle('Match: ' . $freelancerName);
        $n->setBody($score !== null ? sprintf('Match score: %d%%.', (int) round($score * 100)) : 'New match for your request.');
        $n->setPayload([
            'request_id' => $requestId,
            'offer_id' => $offerId,
            'score' => $score,
        ]);
        $this->entityManager->persist($n);
        return $n;
    }

    public function notifyOfferStatusUpdated(User $freelancer, string $status, int $offerId, int $requestId, ?string $message = null): Notification
    {
        $titles = [
            Offer::STATUS_ACCEPTED => 'Offer accepted',
            Offer::STATUS_DECLINED => 'Offer declined',
            Offer::STATUS_REJECTED => 'Offer declined',
            Offer::STATUS_NEGOTIATING => 'Client wants to negotiate',
        ];
        $n = new Notification();
        $n->setUser($freelancer);
        $n->setType(Notification::TYPE_OFFER_STATUS_UPDATED);
        $n->setTitle($titles[$status] ?? 'Offer status updated');
        $n->setBody($message ?? 'Your offer status has been updated.');
        $n->setPayload([
            'offer_id' => $offerId,
            'request_id' => $requestId,
            'status' => $status,
        ]);
        $this->entityManager->persist($n);
        return $n;
    }

    public function notifyClientOfferAction(User $client, int $offerId, string $action): void
    {
        $n = new Notification();
        $n->setUser($client);
        $n->setType(Notification::TYPE_OFFER_STATUS_UPDATED);
        $n->setTitle('Offer ' . strtolower($action));
        $n->setBody('You ' . strtolower($action) . ' an offer.');
        $n->setPayload(['offer_id' => $offerId, 'action' => $action]);
        $this->entityManager->persist($n);
    }
}
