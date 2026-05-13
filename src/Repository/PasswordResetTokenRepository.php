<?php

namespace App\Repository;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordResetToken>
 */
class PasswordResetTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetToken::class);
    }

    /**
     * Find a valid token by raw token from URL.
     * DB stores token_hash (sha256 of raw token); URL contains raw token.
     * Valid = not used, and expires_at > now (expires_at null treated as expired).
     */
    public function findValidByToken(string $rawToken): ?PasswordResetToken
    {
        $tokenHash = hash('sha256', $rawToken);
        $now = new \DateTime();

        $qb = $this->createQueryBuilder('t')
            ->where('t.tokenHash = :hash')
            ->andWhere('t.usedAt IS NULL')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('hash', $tokenHash)
            ->setParameter('now', $now)
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Invalidate all pending tokens for a user (e.g. after successful reset).
     */
    public function invalidateAllForUser(User $user): int
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->update(PasswordResetToken::class, 't')
            ->set('t.usedAt', ':now')
            ->where('t.user = :user')
            ->andWhere('t.usedAt IS NULL')
            ->setParameter('now', new \DateTime())
            ->setParameter('user', $user);
        return (int) $qb->getQuery()->execute();
    }
}
