<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\UserBan;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Repository\UserBanRepository;
use Doctrine\ORM\EntityManagerInterface;

class BanService
{
    private const UTC = 'UTC';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserBanRepository $userBanRepository
    ) {
    }

    /**
     * Ban a user. If already banned, creates a new ban record (update ban = new record).
     *
     * @param \DateInterval|null $duration NULL = permanent
     * @throws \InvalidArgumentException If target is ADMIN and bannedBy is not SUPER_ADMIN, or if banning self
     */
    public function banUser(
        User $user,
        ?User $bannedBy,
        string $reason,
        ?string $note = null,
        ?\DateInterval $duration = null
    ): void {
        if ($bannedBy !== null && $user->getId() === $bannedBy->getId()) {
            throw new \InvalidArgumentException('You cannot ban yourself.');
        }

        $targetRole = $user->getRole();
        if ($targetRole === UserRole::ADMIN && $bannedBy !== null) {
            $byRole = $bannedBy->getRole();
            if ($byRole !== UserRole::SUPER_ADMIN) {
                throw new \InvalidArgumentException('Only a Super Admin can ban an Admin account.');
            }
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone(self::UTC));
        $endsAt = null;
        $banType = UserBan::TYPE_PERM;

        if ($duration !== null) {
            $banType = UserBan::TYPE_TEMP;
            $endsAt = $now->add($duration);
        }

        // Close any active ban record (when updating/re-banning)
        $activeBan = $this->userBanRepository->findActiveBanForUser($user);
        if ($activeBan !== null) {
            $activeBan->setIsActive(false);
            $activeBan->recordLiftedAt($now);
            $activeBan->setLiftReason('Replaced by new ban');
        }

        // Snapshot on User
        $user->setBanned(true);
        $user->setBanReason($reason);
        $user->setBanNote($note);
        $user->recordBannedAt($now);
        $user->recordBanEndsAt($endsAt);
        $user->setBannedBy($bannedBy);
        $user->setBanType($banType);
        $user->setBanCount($user->getBanCount() + 1);
        $user->setStatus(UserStatus::BANNED);

        // History record
        $userBan = new UserBan();
        $userBan->setUser($user);
        $userBan->setBannedBy($bannedBy);
        $userBan->setReason($reason);
        $userBan->setNote($note);
        $userBan->recordBannedAt($now);
        $userBan->recordEndsAt($endsAt);
        $userBan->setType($banType);
        $userBan->setIsActive(true);

        $this->entityManager->persist($userBan);
        $this->entityManager->flush();
    }

    /**
     * Unban a user. Closes the active UserBan record and clears User snapshot.
     */
    public function unbanUser(User $user, ?User $admin = null, ?string $liftReason = null): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone(self::UTC));

        $activeBan = $this->userBanRepository->findActiveBanForUser($user);
        if ($activeBan !== null) {
            $activeBan->setIsActive(false);
            $activeBan->recordLiftedAt($now);
            $activeBan->setLiftReason($liftReason ?? 'Manually lifted');
        }

        $user->setBanned(false);
        $user->setBanReason(null);
        $user->setBanNote(null);
        $user->clearBanSnapshot();
        $user->setBannedBy(null);
        $user->setBanType(null);
        $user->setStatus(UserStatus::ACTIVE);

        $this->entityManager->flush();
    }

    /**
     * Returns true if the user is currently banned (flag set and not expired).
     * If ban has expired, triggers auto-unban and returns false.
     */
    public function isCurrentlyBanned(User $user): bool
    {
        if (!$user->isBanned()) {
            return false;
        }

        $endsAt = $user->getBanEndsAt();
        if ($endsAt === null) {
            return true; // permanent
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone(self::UTC));
        if ($now >= $endsAt) {
            $this->unbanUser($user, null, 'Ban expired');
            return false;
        }

        return true;
    }

    /**
     * If the user is banned and the ban has expired, lift it. Call from request subscriber.
     */
    public function autoUnbanIfExpired(User $user): void
    {
        $this->isCurrentlyBanned($user);
    }
}
