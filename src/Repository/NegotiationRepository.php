<?php

namespace App\Repository;

use App\Entity\Negotiation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Negotiation>
 */
class NegotiationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Negotiation::class);
    }

    /**
     * Find negotiations by offer
     */
    public function findLatestByOffer(\App\Entity\Offer $offer): ?Negotiation
    {
        return $this->createQueryBuilder('n')
            ->innerJoin('n.offer', 'o')->addSelect('o')
            ->innerJoin('n.openedBy', 'u')->addSelect('u')
            ->andWhere('n.offer = :offer')
            ->setParameter('offer', $offer)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find active negotiations for a user
     */
    public function findActiveByUser(int $userId): array
    {
        return $this->createQueryBuilder('n')
            ->innerJoin('n.offer', 'o')->addSelect('o')
            ->innerJoin('n.openedBy', 'u')->addSelect('u')
            ->where('n.openedBy = :userId OR n.targetUser = :userId')
            ->andWhere('n.status IN (:activeStatuses)')
            ->setParameter('userId', $userId)
            ->setParameter('activeStatuses', ['OPEN', 'COUNTERED'])
            ->orderBy('n.lastActionAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}