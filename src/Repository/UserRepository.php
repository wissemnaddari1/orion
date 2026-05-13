<?php

namespace App\Repository;

use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface, UserLoaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPasswordHash($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Load user by email for authentication (no JOIN on face_profiles).
     * Used by security UserProvider to avoid suboptimal LEFT JOIN (~20–30% faster).
     */
    public function loadUserByIdentifier(string $identifier): ?User
    {
        return $this->findOneByEmailForAuth($identifier);
    }

    /**
     * Load user by email for auth; no JOIN on face_profiles.
     */
    public function findOneByEmailForAuth(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('u.email = :email')
            ->setParameter('email', $email)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find user by email or username
     */
    public function findByEmailOrUsername(string $identifier): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('u.email = :identifier')
            ->orWhere('u.username = :identifier')
            ->setParameter('identifier', $identifier)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find active users with face embeddings (for facial recognition)
     * Limit to recently active users to avoid loading entire table
     */
    public function findUsersWithFaceEmbedding(int $limit = 100): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.faceEmbedding IS NOT NULL')
            ->andWhere('u.status = :status')
            ->setParameter('status', UserStatus::ACTIVE)
            ->orderBy('u.lastLogin', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find user by email with face embedding
     */
    public function findByEmailWithFaceEmbedding(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('u.email = :email')
            ->andWhere('u.faceEmbedding IS NOT NULL')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all active users with a face profile (embedding) enrolled.
     *
     * @return User[]
     */
    public function findUsersWithFaceToken(): array
    {
        return $this->createQueryBuilder('u')
            ->innerJoin('App\Entity\FaceProfile', 'fp', 'WITH', 'fp.user = u')
            ->andWhere('u.status = :status')
            ->setParameter('status', UserStatus::ACTIVE)
            ->setMaxResults(500)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all active users with any face enrollment (face profile or legacy image path).
     * Uses INNER JOIN for face_profiles (NOT NULL FK); legacy-only users via separate query.
     *
     * @return User[]
     */
    public function findUsersWithFaceEnrollment(): array
    {
        // INNER JOIN: users with a face_profiles row (NOT NULL FK → INNER is correct and faster)
        $withProfile = $this->createQueryBuilder('u')
            ->innerJoin('App\Entity\FaceProfile', 'fp', 'WITH', 'fp.user = u')
            ->andWhere('u.status = :status')
            ->setParameter('status', UserStatus::ACTIVE)
            ->setMaxResults(500)
            ->getQuery()
            ->getResult();

        $withProfileIds = array_map(static fn (User $u) => $u->getId(), $withProfile);
        if ($withProfileIds === []) {
            $withProfileIds = [0];
        }

        // Legacy: users with faceImagePath but no face_profiles row (no JOIN on face_profiles)
        $legacy = $this->createQueryBuilder('u')
            ->where('u.faceImagePath IS NOT NULL')
            ->andWhere('u.status = :status')
            ->andWhere('u.id NOT IN (:ids)')
            ->setParameter('status', UserStatus::ACTIVE)
            ->setParameter('ids', $withProfileIds)
            ->setMaxResults(500)
            ->getQuery()
            ->getResult();

        return array_merge($withProfile, $legacy);
    }

    /**
     * Search users by keyword (for admin panel), capped at 50 results.
     */
    public function searchUsers(string $keyword): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.username LIKE :keyword')
            ->orWhere('u.email LIKE :keyword')
            ->orWhere('u.firstName LIKE :keyword')
            ->orWhere('u.lastName LIKE :keyword')
            ->setParameter('keyword', '%' . $keyword . '%')
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

    public function findForAdminList(?string $keyword, int $limit, int $offset): array
    {
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($keyword) {
            $qb->andWhere('u.username LIKE :keyword OR u.email LIKE :keyword')
                ->setParameter('keyword', '%' . $keyword . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function countForAdminList(?string $keyword): int
    {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)');

        if ($keyword) {
            $qb->andWhere('u.username LIKE :keyword OR u.email LIKE :keyword')
                ->setParameter('keyword', '%' . $keyword . '%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countAllUsers(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByStatusValue(UserStatus|string $status): int
    {
        $statusValue = $status instanceof UserStatus ? $status->value : $status;

        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.status = :status')
            ->setParameter('status', $statusValue)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByRoleValue(UserRole|string $role): int
    {
        $roleValue = $role instanceof UserRole ? $role->value : $role;

        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.role = :role')
            ->setParameter('role', $roleValue)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPendingCertificates(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.certificateStatus = :status')
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find users that are banned (is_banned = true), most recent first, capped at 200.
     */
    public function findBannedUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.isBanned = true')
            ->orderBy('u.bannedAt', 'DESC')
            ->setMaxResults(200)
            ->getQuery()
            ->getResult();
    }
}
