<?php

namespace App\Repository;

use App\Entity\WorkerCategory;
use App\Entity\WorkerProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkerProfile>
 */
class WorkerProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkerProfile::class);
    }

    /**
     * Find worker profiles by category (for matchmaking candidates).
     *
     * @return WorkerProfile[]
     */
    /**
     * Find worker profiles by category for matchmaking (capped at 100 candidates).
     *
     * @return WorkerProfile[]
     */
    public function findByCategory(WorkerCategory $category): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.workerCategory = :category')
            ->setParameter('category', $category)
            ->orderBy('w.id', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return WorkerProfile[] Returns an array of WorkerProfile objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('w')
    //            ->andWhere('w.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('w.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?WorkerProfile
    //    {
    //        return $this->createQueryBuilder('w')
    //            ->andWhere('w.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
