<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Loads users for authentication using a minimal query (no JOIN on face_profiles).
 * Use this provider to avoid the suboptimal LEFT JOIN flagged by Doctrine Doctor.
 */
final class AppUserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->userRepository->findOneByEmailForAuth($identifier);
        if (!$user instanceof User) {
            throw new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
        }
        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new \InvalidArgumentException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        // Use the same minimal auth query as loadUserByIdentifier() to avoid
        // ORM-generated LEFT JOIN on face_profiles during user refresh.
        $refreshed = $this->userRepository->findOneByEmailForAuth($user->getUserIdentifier());
        if (!$refreshed instanceof User) {
            throw new UserNotFoundException(sprintf('User "%s" not found.', $user->getUserIdentifier()));
        }
        return $refreshed;
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }
}
