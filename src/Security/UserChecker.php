<?php

namespace App\Security;

use App\Entity\User;
use App\Enum\UserStatus;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // Banned users are allowed to authenticate; BanSubscriber redirects them to /banned.

        if (!$user->isEmailVerified()) {
            throw new CustomUserMessageAccountStatusException('Please verify your email before logging in.');
        }

        $status = $user->getStatus();
        if ($status === null || $status !== UserStatus::ACTIVE) {
            throw new CustomUserMessageAccountStatusException('Your account is not active yet.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // No post-auth checks needed.
    }
}
