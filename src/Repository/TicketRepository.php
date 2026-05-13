<?php

namespace App\Repository;

use App\Entity\Ticket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ticket>
 */
class TicketRepository extends ServiceEntityRepository
{
    /**
     * Reusable paginator for ticket lists.
     *
     * @return array{items: Paginator<int, Ticket>, total:int, pages:int, page:int, limit:int}
     */
    public function paginate(int $page = 1, int $limit = 20, array $filters = []): array
    {
        $page = max(1, $page);
        $limit = min(50, max(1, $limit));

        $qb = $this->createQueryBuilder('t')
            ->innerJoin('t.category', 'c')
            ->innerJoin('t.createdBy', 'u')
            ->addSelect('c', 'u');

        $this->applyAdminFilters($qb, $filters);

        $qb->orderBy('t.priority', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $paginator = new Paginator($qb->getQuery(), false);
        $total = count($paginator);

        return [
            'items' => $paginator,
            'total' => $total,
            'pages' => max(1, (int) ceil($total / $limit)),
            'page' => $page,
            'limit' => $limit,
        ];
    }

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    /**
     * Find all tickets created by a specific user
     * Ordered by most recent first
     * 
     * @param int $userId
     * @return Ticket[]
     */
    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('t')
            ->innerJoin('t.category', 'c')
            ->addSelect('c')
            ->andWhere('t.createdBy = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('t.lastMessageAt', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC')
            ->setMaxResults(200)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all tickets for admin panel with optional filters
     * 
     * @param array $filters ['status' => 'OPEN', 'priority' => 'HIGH', 'category' => 1]
     * @return Ticket[]
     */
    public function findForAdmin(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('t')
            ->innerJoin('t.category', 'c')
            ->innerJoin('t.createdBy', 'u')
            ->addSelect('c', 'u');

        $this->applyAdminFilters($qb, $filters);

        return $qb->orderBy('t.priority', 'DESC')
                  ->addOrderBy('t.createdAt', 'DESC')
                  ->setMaxResults(200)
                  ->getQuery()
                  ->getResult();
    }

    private function applyAdminFilters(QueryBuilder $qb, array $filters): void
    {
        if (!empty($filters['status'])) {
            $qb->andWhere('t.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $qb->andWhere('t.priority = :priority')
               ->setParameter('priority', $filters['priority']);
        }

        if (!empty($filters['category'])) {
            $qb->andWhere('t.category = :category')
               ->setParameter('category', $filters['category']);
        }

        if (!empty($filters['acknowledged'])) {
            $qb->andWhere('t.acknowledgedByAd = :acknowledged')
               ->setParameter('acknowledged', $filters['acknowledged']);
        }

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            if ($search !== '') {
                $searchTerm = '%' . strtolower($search) . '%';
                $orConditions = $qb->expr()->orX(
                    'LOWER(u.firstName) LIKE :search',
                    'LOWER(u.lastName) LIKE :search',
                    'LOWER(u.email) LIKE :search',
                    'LOWER(u.username) LIKE :search'
                );

                if (ctype_digit($search)) {
                    $orConditions->add('t.id = :ticketId');
                    $qb->setParameter('ticketId', (int) $search);
                }

                $qb->andWhere($orConditions)
                   ->setParameter('search', $searchTerm);
            }
        }
    }

    /**
     * Count unread/unacknowledged tickets for admin dashboard
     * 
     * @return int
     */
    public function countUnacknowledged(): int
    {
        return $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.acknowledgedByAd = false')
            ->andWhere('t.status != :closed')
            ->setParameter('closed', 'CLOSED')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find one ticket by ID and verify user ownership
     * Returns null if ticket doesn't exist or user doesn't own it
     * 
     * @param int $ticketId
     * @param int $userId
     * @return Ticket|null
     */
    public function findOneByIdAndUser(int $ticketId, int $userId): ?Ticket
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.id = :ticketId')
            ->andWhere('t.createdBy = :userId')
            ->setParameter('ticketId', $ticketId)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get ticket statistics for admin dashboard
     * 
     * @return array
     */
    public function getStatistics(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = "OPEN" THEN 1 ELSE 0 END) as open_count,
                SUM(CASE WHEN status = "IN_PROGRESS" THEN 1 ELSE 0 END) as in_progress_count,
                SUM(CASE WHEN status = "CLOSED" THEN 1 ELSE 0 END) as closed_count,
                SUM(CASE WHEN acknowledged_by_ad = 0 THEN 1 ELSE 0 END) as unacknowledged_count
            FROM ticket
        ';
        
        return $conn->fetchAssociative($sql);
    }
}
