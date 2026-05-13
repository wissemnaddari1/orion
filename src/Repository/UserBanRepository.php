<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserBan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserBan>
 */
class UserBanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserBan::class);
    }

    public function findActiveBanForUser(User $user): ?UserBan
    {
        return $this->createQueryBuilder('ub')
            ->where('ub.user = :user')
            ->andWhere('ub.isActive = true')
            ->setParameter('user', $user)
            ->orderBy('ub.bannedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return UserBan[]
     */
    public function findLastBansForUser(User $user, int $limit = 5): array
    {
        return $this->createQueryBuilder('ub')
            ->where('ub.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ub.bannedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
