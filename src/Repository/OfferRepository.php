<?php

namespace App\Repository;

use App\Entity\Offer;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Offer>
 */
class OfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Offer::class);
    }

    /**
     * Find offers for a worker with JOINs (ServiceRequest + Client), search and status filter, paginated.
     * Avoids N+1 and allows filtering by service request title/description and client.
     *
     * @return array{offers: list<Offer>, total: int}
     */
    public function findForWorker(User $worker, ?string $q, ?string $status, int $page = 1, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('o')
            ->innerJoin('o.serviceRequest', 'sr')->addSelect('sr')
            ->innerJoin('sr.client', 'c')->addSelect('c')
            ->leftJoin('o.negotiation', 'neg')->addSelect('neg')
            ->where('o.worker = :worker')
            ->setParameter('worker', $worker)
            ->orderBy('o.createdAt', 'DESC');

        if ($q !== null && $q !== '') {
            $qb->andWhere(
                '(LOWER(sr.title) LIKE :q OR LOWER(sr.description) LIKE :q OR LOWER(c.username) LIKE :q)'
            )->setParameter('q', '%' . strtolower($q) . '%');
        }

        if ($status !== null && $status !== '' && $status !== 'all') {
            $qb->andWhere('o.status = :status')
                ->setParameter('status', strtoupper($status));
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(DISTINCT o.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $query = $qb->setFirstResult(($page - 1) * $limit)->setMaxResults($limit)->getQuery();
        $paginator = new Paginator($query, true);
        $offers = iterator_to_array($paginator);

        return ['offers' => $offers, 'total' => $total];
    }

    /**
     * Find offers by service request
     */
    public function findByServiceRequest(int $serviceRequestId): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.serviceRequest = :serviceRequestId')
            ->setParameter('serviceRequestId', $serviceRequestId)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find offers by worker
     */
    public function findByWorker(int $workerId): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.worker = :workerId')
            ->setParameter('workerId', $workerId)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find offers by status
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.status = :status')
            ->setParameter('status', $status)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Optimized find for client index with eager loading.
     */
    public function findForClientIndex(User $client, ?string $q, ?string $status, string $sort, int $page, int $limit): array
    {
        $qb = $this->createQueryBuilder('o')
            ->innerJoin('o.serviceRequest', 'sr')->addSelect('sr')
            ->innerJoin('o.worker', 'w')->addSelect('w')
            ->leftJoin('o.negotiation', 'neg')->addSelect('neg')
            ->where('sr.client = :client')
            ->setParameter('client', $client);

        if ($status !== null && $status !== '' && $status !== 'all') {
            $qb->andWhere('o.status = :status')
               ->setParameter('status', strtoupper($status));
        }

        if ($q !== null && $q !== '') {
            $qb->andWhere('w.firstName LIKE :query OR w.lastName LIKE :query OR sr.title LIKE :query')
               ->setParameter('query', '%' . $q . '%');
        }

        match ($sort) {
            'oldest' => $qb->orderBy('o.createdAt', 'ASC'),
            'price_low' => $qb->orderBy('o.price', 'ASC'),
            'price_high' => $qb->orderBy('o.price', 'DESC'),
            'fastest' => $qb->orderBy('o.estimatedTimeDays', 'ASC'),
            'slowest' => $qb->orderBy('o.estimatedTimeDays', 'DESC'),
            default => $qb->orderBy('o.createdAt', 'DESC'),
        };

        $countQb = $this->createQueryBuilder('o')
            ->select('COUNT(DISTINCT o.id)')
            ->innerJoin('o.serviceRequest', 'sr')
            ->where('sr.client = :client')
            ->setParameter('client', $client);

        if ($q) {
            $countQb->innerJoin('o.worker', 'w')
                ->andWhere('w.firstName LIKE :query OR w.lastName LIKE :query OR sr.title LIKE :query')
                ->setParameter('query', '%' . $q . '%');
        }

        if ($status !== 'all') {
            $countQb->andWhere('o.status = :status')
                ->setParameter('status', strtoupper($status));
        }

        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        $query = $qb->setFirstResult(($page - 1) * $limit)->setMaxResults($limit)->getQuery();
        $paginator = new Paginator($query, true);
        $offers = iterator_to_array($paginator);

        return ['offers' => $offers, 'total' => $total];
    }

    /**
     * Get aggregate stats for client offers in a single query.
     */
    public function getOffersStatsForClient(User $client): array
    {
        $stats = $this->createQueryBuilder('o')
            ->select('AVG(o.price) as avgPrice, MIN(o.estimatedTimeDays) as fastestDelivery')
            ->innerJoin('o.serviceRequest', 'sr')
            ->where('sr.client = :client')
            ->setParameter('client', $client)
            ->getQuery()
            ->getOneOrNullResult();

        $counts = $this->createQueryBuilder('o')
            ->select('o.status, COUNT(DISTINCT o.id) as count')
            ->innerJoin('o.serviceRequest', 'sr')
            ->where('sr.client = :client')
            ->setParameter('client', $client)
            ->groupBy('o.status')
            ->getQuery()
            ->getResult();

        $activeBreakdown = ['PENDING' => 0, 'NEGOTIATING' => 0];
        foreach ($counts as $row) {
            if (isset($activeBreakdown[$row['status']])) {
                $activeBreakdown[$row['status']] = (int) $row['count'];
            }
        }

        return [
            'avgPrice' => $stats['avgPrice'] ?? 0,
            'fastestDelivery' => $stats['fastestDelivery'] ?? 0,
            'activeBreakdown' => $activeBreakdown,
            'activeTotal' => array_sum($activeBreakdown)
        ];
    }

    /**
     * Batch count offers by service request IDs.
     */
    public function countByServiceRequestIds(array $srIds): array
    {
        if (empty($srIds)) return [];

        $results = $this->createQueryBuilder('o')
            ->select('sr.id as srId, COUNT(DISTINCT o.id) as count')
            ->innerJoin('o.serviceRequest', 'sr')
            ->where('sr.id IN (:ids)')
            ->setParameter('ids', $srIds)
            ->groupBy('sr.id')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $res) {
            $counts[$res['srId']] = (int) $res['count'];
        }
        return $counts;
    }
}
