<?php

declare(strict_types=1);

namespace App\Security\Csrf;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Stateless CSRF token manager (no PHP sessions).
 *
 * Token value is a deterministic HMAC based on:
 * - a per-client seed cookie (CSRF_SEED)
 * - token ID
 * - current user identifier (when authenticated)
 *
 * This keeps Twig's csrf_token() and controllers' isCsrfTokenValid() working
 * without ever starting a session.
 */
final class StatelessCsrfTokenManager implements CsrfTokenManagerInterface
{
    public function __construct(
        private readonly string $secret,
        private readonly ?RequestCsrfSeedProvider $seedProvider = null,
        private readonly ?TokenStorageInterface $tokenStorage = null,
    ) {
    }

    public function getToken(string $tokenId): CsrfToken
    {
        return new CsrfToken($tokenId, $this->computeValue($tokenId));
    }

    public function refreshToken(string $tokenId): CsrfToken
    {
        // Deterministic: refresh == get
        return $this->getToken($tokenId);
    }

    public function removeToken(string $tokenId): ?string
    {
        // Stateless: nothing stored server-side
        return null;
    }

    public function isTokenValid(CsrfToken $token): bool
    {
        $expected = $this->computeValue($token->getId());

        return hash_equals($expected, (string) $token->getValue());
    }

    private function computeValue(string $tokenId): string
    {
        $seed = $this->seedProvider ? $this->seedProvider->getSeed() : 'fixed_seed';

        $user = $this->tokenStorage ? $this->tokenStorage->getToken()?->getUser() : null;
        $userId = $user?->getUserIdentifier() ?? 'anon';

        $data = $seed . ':' . $tokenId . ':' . $userId;
        $mac = hash_hmac('sha256', $data, $this->secret, true);

        return rtrim(strtr(base64_encode($mac), '+/', '-_'), '=');
    }
}

