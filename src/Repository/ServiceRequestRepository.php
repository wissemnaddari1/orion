<?php


namespace App\Repository;

use App\DTO\AggregateAmountDto;
use App\DTO\AggregateCountDto;
use App\Entity\ServiceRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServiceRequest>
 */
class ServiceRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceRequest::class);
    }

    /**
     * Find service requests by status (capped at 200 most recent).
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('sr')
            ->andWhere('sr.status = :status')
            ->setParameter('status', $status)
            ->orderBy('sr.createdAt', 'DESC')
            ->setMaxResults(200)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find service requests by client (capped at 200 most recent).
     */
    public function findByClient(int $clientId): array
    {
        return $this->createQueryBuilder('sr')
            ->andWhere('sr.client = :clientId')
            ->setParameter('clientId', $clientId)
            ->orderBy('sr.createdAt', 'DESC')
            ->setMaxResults(200)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count service requests by client. Uses result cache to avoid repeated queries in same request.
     */
    public function countByClient(User $client, ?int $cacheTtlSeconds = 60): int
    {
        $qb = $this->createQueryBuilder('sr')
            ->select('COUNT(sr.id)')
            ->where('sr.client = :client')
            ->setParameter('client', $client);
        $query = $qb->getQuery();
        if ($cacheTtlSeconds > 0) {
            $query->enableResultCache($cacheTtlSeconds, 'sr_count_client_' . $client->getId());
        }
        return (int) $query->getSingleScalarResult();
    }

    /**
     * Recent service requests by client with category joined (avoids N+1 on category).
     *
     * @return ServiceRequest[]
     */
    public function findRecentByClientWithCategory(User $client, int $limit = 5): array
    {
        return $this->createQueryBuilder('sr')
            ->addSelect('c')
            ->leftJoin('sr.category', 'c')
            ->where('sr.client = :client')
            ->setParameter('client', $client)
            ->orderBy('sr.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Service requests by client for list page: category joined (avoids N+1), ORDER BY + LIMIT.
     *
     * @return ServiceRequest[]
     */
    public function findForClientList(User $client, int $limit = 200): array
    {
        return $this->createQueryBuilder('sr')
            ->addSelect('c')
            ->leftJoin('sr.category', 'c')
            ->where('sr.client = :client')
            ->setParameter('client', $client)
            ->orderBy('sr.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Activity (count per day) and budget totals for a client in one query set.
     *
     * @return array{activity: array<string, int>, budget_min: float, budget_max: float}
     */
    public function getActivityAndBudgetTotalsByClient(User $client): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $clientId = $client->getId();
        $since = (new \DateTime('-30 days'))->format('Y-m-d');
        $activitySql = 'SELECT DATE(created_at) as day, COUNT(*) as cnt FROM service_request WHERE client_id = ? AND created_at >= ? GROUP BY DATE(created_at)';
        $budgetSql = 'SELECT COALESCE(SUM(CAST(budget_min AS DECIMAL(14,2))), 0) as min_sum, COALESCE(SUM(CAST(budget_max AS DECIMAL(14,2))), 0) as max_sum FROM service_request WHERE client_id = ?';
        $activityRows = $conn->executeQuery($activitySql, [$clientId, $since])->fetchAllAssociative();
        $budgetRow = $conn->executeQuery($budgetSql, [$clientId])->fetchAssociative();
        $activityMap = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = (new \DateTime("-{$i} days"))->format('Y-m-d');
            $activityMap[$day] = 0;
        }
        foreach ($activityRows as $row) {
            $day = $row['day'] ?? '';
            if ($day instanceof \DateTimeInterface) {
                $day = $day->format('Y-m-d');
            }
            $day = (string) $day;
            if (isset($activityMap[$day])) {
                $activityMap[$day] = (int) $row['cnt'];
            }
        }
        return [
            'activity' => $activityMap,
            'budget_min' => (float) ($budgetRow['min_sum'] ?? 0),
            'budget_max' => (float) ($budgetRow['max_sum'] ?? 0),
        ];
    }

    /**
     * Find service requests by title or the client's username (capped at 100).
     */
    public function findByTitleOrUsername(string $searchTerm): array
    {
        return $this->createQueryBuilder('sr')
            ->leftJoin('sr.client', 'u')
            ->addSelect('u')
            ->where('sr.title LIKE :term')
            ->orWhere('u.username LIKE :term')
            ->setParameter('term', '%' . $searchTerm . '%')
            ->orderBy('sr.createdAt', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
    }

    /**
     * Fetches services with joined clients for admin index, with pagination.
     *
     * @return array{items: ServiceRequest[], total: int, totalPages: int}
     */
    public function findForAdminIndex(
        ?string $search = null,
        ?string $sort = null,
        ?int $categoryId = null,
        int $page = 1,
        int $limit = 20
    ): array {
        $limit = min(100, max(1, $limit));
        $page  = max(1, $page);

        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.client', 'c')
            ->leftJoin('s.category', 'cat')
            ->addSelect('c', 'cat');

        if ($search) {
            $qb->andWhere('s.title LIKE :term OR c.username LIKE :term')
               ->setParameter('term', '%' . $search . '%');
        }

        if ($categoryId) {
            $qb->andWhere('cat.id = :catId')
               ->setParameter('catId', $categoryId);
        }

        switch ($sort) {
            case 'username_asc':
                $qb->orderBy('c.username', 'ASC');
                break;
            case 'username_desc':
                $qb->orderBy('c.username', 'DESC');
                break;
            case 'duration_asc':
                $qb->orderBy('s.duration', 'ASC');
                break;
            case 'duration_desc':
                $qb->orderBy('s.duration', 'DESC');
                break;
            case 'budget_desc':
                $qb->orderBy('s.budget_max', 'DESC');
                break;
            case 'budget_asc':
                $qb->orderBy('s.budget_min', 'ASC');
                break;
            case 'date_oldest':
                $qb->orderBy('s.createdAt', 'ASC');
                break;
            case 'date_newest':
            default:
                $qb->orderBy('s.createdAt', 'DESC');
                break;
        }

        $total = (int) (clone $qb)
            ->select('COUNT(DISTINCT s.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $query = $qb->setFirstResult(($page - 1) * $limit)->setMaxResults($limit)->getQuery();
        $paginator = new Paginator($query, true);
        $items = iterator_to_array($paginator);

        return [
            'items'      => $items,
            'total'      => $total,
            'totalPages' => max(1, (int) ceil($total / $limit)),
        ];
    }
    public function getAdminStats(): array
    {
        $countDtoClass = AggregateCountDto::class;

        // 1. Demand by Category
        $categoryRows = $this->createQueryBuilder('s')
            ->select(sprintf('NEW %s(cat.name, COUNT(DISTINCT s.id))', $countDtoClass))
            ->join('s.category', 'cat')
            ->groupBy('cat.id, cat.name')
            ->orderBy('COUNT(DISTINCT s.id)', 'DESC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();

        // 2. Average Max Budget
        $avgBudget = $this->createQueryBuilder('s')
            ->select('AVG(s.budget_max)')
            ->getQuery()
            ->getSingleScalarResult();

        // 3. Status/Level Distribution
        $levelRows = $this->createQueryBuilder('s')
            ->select(sprintf('NEW %s(s.level, COUNT(DISTINCT s.id))', $countDtoClass))
            ->groupBy('s.level')
            ->getQuery()
            ->getResult();

        $categoryStats = array_map(
            static fn (AggregateCountDto $row): array => [
                'name' => $row->getLabel(),
                'count' => $row->getTotal(),
            ],
            $categoryRows
        );

        $levelStats = array_map(
            static fn (AggregateCountDto $row): array => [
                'level' => $row->getLabel(),
                'count' => $row->getTotal(),
            ],
            $levelRows
        );

        return [
            'categories' => $categoryStats,
            'avgBudget' => round($avgBudget ?? 0),
            'levels' => $levelStats,
        ];
    }

    public function findByTitleOnly(string $searchTerm): array
    {
        return $this->createQueryBuilder('sr')
            ->where('sr.title LIKE :term')
            ->setParameter('term', '%' . $searchTerm . '%')
            ->orderBy('sr.createdAt', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
    }
    public function countByCategory(): array
    {
        $countDtoClass = AggregateCountDto::class;
        $rows = $this->createQueryBuilder('sr')
            ->select(sprintf('NEW %s(c.name, COUNT(DISTINCT sr.id))', $countDtoClass))
            ->innerJoin('sr.category', 'c')
            ->groupBy('c.id, c.name')
            ->orderBy('COUNT(DISTINCT sr.id)', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (AggregateCountDto $row): array => [
                'category' => $row->getLabel(),
                'total' => $row->getTotal(),
            ],
            $rows
        );
    }

    public function countByStatus(): array
    {
        $countDtoClass = AggregateCountDto::class;
        $rows = $this->createQueryBuilder('sr')
            ->select(sprintf('NEW %s(sr.level, COUNT(sr.id))', $countDtoClass))
            ->groupBy('sr.level')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (AggregateCountDto $row): array => [
                'level' => $row->getLabel(),
                'total' => $row->getTotal(),
            ],
            $rows
        );
    }

    public function findSince(\DateTime $since): array
    {
        return $this->createQueryBuilder('sr')
            ->select('sr.createdAt')
            ->where('sr.createdAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('sr.createdAt', 'ASC')
            ->setMaxResults(1000)
            ->getQuery()
            ->getResult();
    }

    public function getAllBudgetMax(): array
    {
        return $this->createQueryBuilder('sr')
            ->select('sr.budget_max')
            ->orderBy('sr.id', 'DESC')
            ->setMaxResults(1000)
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * @return array<int, array{day: string, total: int}>
     */
    public function getDailyRequestCountsSince(\DateTimeInterface $since): array
    {
        $sql = 'SELECT DATE(created_at) AS day, COUNT(*) AS total
                FROM service_request
                WHERE created_at >= :since
                GROUP BY DATE(created_at)';

        $rows = $this->getEntityManager()
            ->getConnection()
            ->executeQuery($sql, ['since' => $since->format('Y-m-d H:i:s')])
            ->fetchAllAssociative();

        return array_map(
            static function (array $row): array {
                $day = $row['day'] ?? '';
                if ($day instanceof \DateTimeInterface) {
                    $day = $day->format('Y-m-d');
                }

                return [
                    'day' => (string) $day,
                    'total' => (int) ($row['total'] ?? 0),
                ];
            },
            $rows
        );
    }

    /**
     * @return array<string, int>
     */
    public function getBudgetRangeDistribution(): array
    {
        $sql = 'SELECT
                    SUM(CASE WHEN budget_max <= 500 THEN 1 ELSE 0 END) AS range_0_500,
                    SUM(CASE WHEN budget_max > 500 AND budget_max <= 1500 THEN 1 ELSE 0 END) AS range_500_1500,
                    SUM(CASE WHEN budget_max > 1500 AND budget_max <= 3000 THEN 1 ELSE 0 END) AS range_1500_3000,
                    SUM(CASE WHEN budget_max > 3000 AND budget_max <= 6000 THEN 1 ELSE 0 END) AS range_3000_6000,
                    SUM(CASE WHEN budget_max > 6000 THEN 1 ELSE 0 END) AS range_6000_plus
                FROM service_request';

        $row = $this->getEntityManager()
            ->getConnection()
            ->executeQuery($sql)
            ->fetchAssociative() ?: [];

        return [
            '0-500' => (int) ($row['range_0_500'] ?? 0),
            '500-1500' => (int) ($row['range_500_1500'] ?? 0),
            '1500-3000' => (int) ($row['range_1500_3000'] ?? 0),
            '3000-6000' => (int) ($row['range_3000_6000'] ?? 0),
            '6000+' => (int) ($row['range_6000_plus'] ?? 0),
        ];
    }

    public function getSpendingByCategory(User $user, int $limit = 50): array
    {
        $safeLimit = max(0, (int) $limit);
        $amountDtoClass = AggregateAmountDto::class;

        $qb = $this->createQueryBuilder('sr')
            ->select(sprintf('NEW %s(c.name, SUM(sr.budget_max))', $amountDtoClass))
            ->innerJoin('sr.category', 'c')
            ->where('sr.client = :client')
            ->setParameter('client', $user)
            ->groupBy('c.id, c.name')
            ->orderBy('SUM(sr.budget_max)', 'DESC');

        $rows = $qb->getQuery()->getResult();
        if ($safeLimit > 0) {
            $rows = array_slice($rows, 0, $safeLimit);
        }

        return array_map(
            static fn (AggregateAmountDto $row): array => [
                'category' => $row->getLabel(),
                'total' => $row->getTotal(),
            ],
            $rows
        );
    }
}
