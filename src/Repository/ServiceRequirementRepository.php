<?php

namespace App\Repository;

use App\DTO\AggregateCountDto;
use App\Entity\ServiceRequirement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServiceRequirement>
 */
class ServiceRequirementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceRequirement::class);
    }

    //    /**
    //     * @return ServiceRequirement[] Returns an array of ServiceRequirement objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?ServiceRequirement
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
    public function countByPriority(): array
    {
        $countDtoClass = AggregateCountDto::class;
        $rows = $this->createQueryBuilder('r')
            ->select(sprintf('NEW %s(r.priority_level, COUNT(r.id))', $countDtoClass))
            ->groupBy('r.priority_level')
            ->orderBy('r.priority_level', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (AggregateCountDto $row): array => [
                'priority' => $row->getLabel(),
                'total' => $row->getTotal(),
            ],
            $rows
        );
    }

}
