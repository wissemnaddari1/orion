<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function countUnreadByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.user = :user')
            ->andWhere('n.isRead = :read')
            ->setParameter('user', $user)
            ->setParameter('read', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Notification[]
     */
    public function findLatestForUser(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.user = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count unread notifications that have an offer_id in payload and whose offer is still PENDING or NEGOTIATING.
     */
    public function countUnreadOfferOnlyByUser(User $user): int
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = "SELECT COUNT(n.id) AS cnt FROM notification n
                WHERE n.user_id = ? AND n.is_read = 0
                AND n.payload IS NOT NULL AND JSON_EXTRACT(n.payload, '$.offer_id') IS NOT NULL
                AND EXISTS (
                    SELECT 1
                    FROM offer o
                    WHERE o.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(n.payload, '$.offer_id')) AS UNSIGNED)
                      AND o.status IN ('PENDING', 'NEGOTIATING')
                )";
        $result = $conn->executeQuery($sql, [$user->getId()]);
        return (int) $result->fetchOne();
    }

    /**
     * Latest notifications that have an offer_id in payload and whose offer is still PENDING or NEGOTIATING.
     *
     * @return Notification[]
     */
    public function findLatestOfferOnlyForUser(User $user, int $limit = 10): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $limit = (int) $limit;
        $sql = "SELECT n.id FROM notification n
                WHERE n.user_id = ?
                AND n.payload IS NOT NULL AND JSON_EXTRACT(n.payload, '$.offer_id') IS NOT NULL
                AND EXISTS (
                    SELECT 1
                    FROM offer o
                    WHERE o.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(n.payload, '$.offer_id')) AS UNSIGNED)
                      AND o.status IN ('PENDING', 'NEGOTIATING')
                )
                ORDER BY n.created_at DESC
                LIMIT " . $limit;
        $ids = $conn->executeQuery($sql, [$user->getId()])->fetchFirstColumn();
        if ($ids === []) {
            return [];
        }
        return $this->createQueryBuilder('n')
            ->where('n.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count notifications for this user that have request_id and offer_id in payload (client offer notifications for a request).
     */
    public function countClientOfferNotificationsForRequest(User $user, int $requestId): int
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = "SELECT COUNT(n.id) AS cnt FROM notification n
                WHERE n.user_id = ?
                AND n.payload IS NOT NULL
                AND CAST(JSON_UNQUOTE(JSON_EXTRACT(n.payload, '$.request_id')) AS UNSIGNED) = ?
                AND JSON_EXTRACT(n.payload, '$.offer_id') IS NOT NULL";
        $result = $conn->executeQuery($sql, [$user->getId(), $requestId]);
        return (int) $result->fetchOne();
    }

    /**
     * Delete notifications for this user that have the given offer_id in payload.
     * Used when a freelancer accepts so their "New request match" notification disappears.
     */
    public function deleteByUserAndOfferId(User $user, int $offerId): int
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = "DELETE FROM notification WHERE user_id = ?
                AND payload IS NOT NULL
                AND CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.offer_id')) AS UNSIGNED) = ?";
        return (int) $conn->executeStatement($sql, [$user->getId(), $offerId]);
    }

    public function findOneByIdAndUser(int $id, User $user): ?Notification
    {
        return $this->createQueryBuilder('n')
            ->where('n.id = :id')
            ->andWhere('n.user = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Delete read notifications older than the given date.
     * Returns the number of deleted rows.
     */
    public function deleteReadOlderThan(\DateTimeInterface $before): int
    {
        $qb = $this->createQueryBuilder('n')
            ->delete()
            ->where('n.isRead = :read')
            ->andWhere('n.createdAt < :before')
            ->setParameter('read', true)
            ->setParameter('before', $before);

        return (int) $qb->getQuery()->execute();
    }

    /**
     * Delete all notifications (read or unread) older than the given date.
     * Returns the number of deleted rows.
     */
    public function deleteAllOlderThan(\DateTimeInterface $before): int
    {
        $qb = $this->createQueryBuilder('n')
            ->delete()
            ->where('n.createdAt < :before')
            ->setParameter('before', $before);

        return (int) $qb->getQuery()->execute();
    }
}
