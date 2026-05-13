<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Short-lived temp token for 2FA step (5 min). Stored in cache; only valid for /api/auth/2fa/verify.
 */
final class TwoFactorTempTokenService
{
    private const TTL_SECONDS = 300; // 5 minutes
    private const CACHE_PREFIX = '2fa_temp_';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * Create a temp token for the user. Returns the token string (opaque, not JWT).
     */
    public function create(User $user): string
    {
        $token = bin2hex(random_bytes(32));
        $key = self::CACHE_PREFIX . hash('sha256', $token);

        $this->cache->get($key, function (ItemInterface $item) use ($user): string {
            $item->expiresAfter(self::TTL_SECONDS);

            return $user->getUserIdentifier();
        });

        return $token;
    }

    /**
     * Consume temp token: return user if valid, then delete token. Returns null if invalid/expired.
     */
    public function consume(string $token): ?User
    {
        $key = self::CACHE_PREFIX . hash('sha256', $token);

        try {
            $email = $this->cache->get($key, function (ItemInterface $item) {
                $item->expiresAfter(0);

                return null;
            });
        } catch (\Psr\Cache\InvalidArgumentException $e) {
            return null;
        }

        if ($email === null || $email === '') {
            return null;
        }

        $this->cache->delete($key);

        $user = $this->userRepository->findOneBy(['email' => $email]);

        return $user instanceof User ? $user : null;
    }
}
