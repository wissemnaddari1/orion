<?php

namespace App\Repository;

use App\Entity\WorkerCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkerCategory>
 */
class WorkerCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkerCategory::class);
    }

    /**
     * Find active categories ordered by display_order then name (for dropdowns/selects).
     *
     * @return WorkerCategory[]
     */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('c.display_order', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all categories for dropdown/select use (capped at 200 rows to prevent unbounded SELECT).
     *
     * @return WorkerCategory[]
     */
    public function findAllForDropdown(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.display_order', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->setMaxResults(200)
            ->getQuery()
            ->getResult();
    }

    /**
     * Search by name or description (LIKE), ordered by display_order ASC then name ASC.
     *
     * @return WorkerCategory[]
     */
    public function searchByNameOrDescription(?string $query): array
    {
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.display_order', 'ASC')
            ->addOrderBy('c.name', 'ASC');

        if ($query !== null && trim($query) !== '') {
            $qb->andWhere('c.name LIKE :q OR c.description LIKE :q')
                ->setParameter('q', '%' . trim($query) . '%');
        }

        return $qb->setMaxResults(200)->getQuery()->getResult();
    }

//    /**
//     * @return WorkerCategory[] Returns an array of WorkerCategory objects
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

//    public function findOneBySomeField($value): ?WorkerCategory
//    {
//        return $this->createQueryBuilder('w')
//            ->andWhere('w.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
