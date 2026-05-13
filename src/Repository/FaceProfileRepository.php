<?php

namespace App\Repository;

use App\Entity\FaceProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FaceProfile>
 */
class FaceProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FaceProfile::class);
    }

    /**
     * Load all face profiles (ALL users) with id, user id and embedding for match.
     *
     * Security note:
     * We intentionally DO NOT filter out banned/inactive users here. If we exclude them,
     * a banned user's face may "match" the closest remaining active user and cause an
     * identity switch. We must allow matching to resolve the correct user first,
     * then enforce eligibility checks (banned/inactive) in the authentication layer.
     *
     * @return list<array{id: int, user_id: int, embedding: array<float>}>
     */
    public function findAllForMatch(): array
    {
        $qb = $this->createQueryBuilder('fp')
            ->select('fp.id', 'fp.embedding', 'u.id AS user_id')
            ->innerJoin('fp.user', 'u');

        $rows = $qb->getQuery()->getArrayResult();
        $out = [];
        foreach ($rows as $row) {
            $embedding = $row['embedding'] ?? [];
            if (!\is_array($embedding) || empty($embedding)) {
                continue;
            }
            $out[] = [
                'id' => (int) $row['id'],
                'user_id' => (int) $row['user_id'],
                'embedding' => array_map('floatval', $embedding),
            ];
        }
        return $out;
    }

    public function findOneByUser(int $userId): ?FaceProfile
    {
        return $this->createQueryBuilder('fp')
            ->innerJoin('fp.user', 'u')
            ->where('u.id = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function touchLastMatchedAtByUserId(int $userId): void
    {
        $this->createQueryBuilder('fp')
            ->update()
            ->set('fp.lastMatchedAt', ':now')
            ->where('IDENTITY(fp.user) = :userId')
            ->setParameter('now', new \DateTime())
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }
}
