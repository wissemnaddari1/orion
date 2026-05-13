<?php

namespace App\Repository;

use App\Entity\SubTicket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SubTicket>
 */
class SubTicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubTicket::class);
    }

    /**
     * Find all messages for a specific ticket
     * Ordered chronologically (oldest first)
     * Excludes deleted messages
     * For non-admin users, exclude internal notes
     * 
     * @param int $ticketId
     * @param bool $isAdmin
     * @return SubTicket[]
     */
    public function findByTicket(int $ticketId, bool $isAdmin = false, int $limit = 200): array
    {
        $limit = min(1000, max(1, $limit));
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.sender', 'u')
            ->addSelect('u')
            ->andWhere('s.ticket = :ticketId')
            ->andWhere('s.isDeleted = false')
            ->setParameter('ticketId', $ticketId)
            ->orderBy('s.createdAt', 'ASC')
            ->setMaxResults($limit);

        // Non-admin users cannot see internal notes
        if (!$isAdmin) {
            $qb->andWhere('s.isInternal = false');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Count unread messages in a ticket for the ticket owner
     * 
     * @param int $ticketId
     * @return int
     */
    public function countUnreadByTicket(int $ticketId): int
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.ticket = :ticketId')
            ->andWhere('s.isRead = false')
            ->andWhere('s.isDeleted = false')
            ->andWhere('s.isInternal = false')  // Don't count internal notes
            ->setParameter('ticketId', $ticketId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count unread messages in a ticket for a specific user (excluding their own messages)
     * 
     * @param int $ticketId
     * @param int $userId
     * @return int
     */
    public function countUnreadForUser(int $ticketId, int $userId): int
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.ticket = :ticketId')
            ->andWhere('s.sender != :userId')  // Don't count user's own messages
            ->andWhere('s.isRead = false')
            ->andWhere('s.isDeleted = false')
            ->andWhere('s.isInternal = false')  // Don't count internal notes
            ->setParameter('ticketId', $ticketId)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Batch count unread messages for multiple tickets at once (avoids N+1)
     *
     * @param int[] $ticketIds
     * @return array<int, int> Map of ticketId => unreadCount
     */
    public function countUnreadForUserByTickets(array $ticketIds, int $userId): array
    {
        if (empty($ticketIds)) {
            return [];
        }

        $rows = $this->createQueryBuilder('s')
            ->select('IDENTITY(s.ticket) AS ticketId, COUNT(s.id) AS cnt')
            ->andWhere('s.ticket IN (:ticketIds)')
            ->andWhere('s.sender != :userId')
            ->andWhere('s.isRead = false')
            ->andWhere('s.isDeleted = false')
            ->andWhere('s.isInternal = false')
            ->setParameter('ticketIds', $ticketIds)
            ->setParameter('userId', $userId)
            ->groupBy('s.ticket')
            ->getQuery()
            ->getResult();

        $map = array_fill_keys($ticketIds, 0);
        foreach ($rows as $row) {
            $map[(int) $row['ticketId']] = (int) $row['cnt'];
        }

        return $map;
    }

    /**
     * Mark all messages in a ticket as read
     * 
     * @param int $ticketId
     * @param int $userId (to ensure only non-sender messages are marked)
     * @return int Number of messages marked as read
     */
    public function markAsReadByTicket(int $ticketId, int $userId): int
    {
        return $this->createQueryBuilder('s')
            ->update()
            ->set('s.isRead', 'true')
            ->set('s.readAt', ':now')
            ->andWhere('s.ticket = :ticketId')
            ->andWhere('s.sender != :userId')  // Don't mark own messages as read
            ->andWhere('s.isRead = false')
            ->setParameter('ticketId', $ticketId)
            ->setParameter('userId', $userId)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }
}
