<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AiRecommendation;
use App\Entity\ServiceRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AiRecommendation>
 */
class AiRecommendationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiRecommendation::class);
    }

    /**
     * @return list<AiRecommendation>
     */
    public function findLatestForService(ServiceRequest $serviceRequest, ?User $requestedBy = null, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.serviceRequest = :serviceRequest')
            ->setParameter('serviceRequest', $serviceRequest)
            ->orderBy('r.score', 'DESC')
            ->addOrderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($requestedBy !== null) {
            $qb->andWhere('r.requestedBy = :requestedBy')
                ->setParameter('requestedBy', $requestedBy);
        }

        return $qb->getQuery()->getResult();
    }
}

