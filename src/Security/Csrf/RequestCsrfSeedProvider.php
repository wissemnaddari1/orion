<?php

declare(strict_types=1);

namespace App\Security\Csrf;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a per-client seed for CSRF tokens without using PHP sessions.
 *
 * - Reads from a HttpOnly cookie (CSRF_SEED) when present
 * - Otherwise generates a seed and stores it on the Request attributes
 *   (a response subscriber will persist it as a cookie)
 */
final class RequestCsrfSeedProvider
{
    public const COOKIE_NAME = 'CSRF_SEED';
    private const ATTR_NAME = '_csrf_seed';

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function getSeed(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            // CLI context (e.g. cache warmup) - deterministic fallback
            return 'cli';
        }

        $cookie = $request->cookies->get(self::COOKIE_NAME);
        if (is_string($cookie) && $cookie !== '') {
            return $cookie;
        }

        $attr = $request->attributes->get(self::ATTR_NAME);
        if (is_string($attr) && $attr !== '') {
            return $attr;
        }

        $seed = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $request->attributes->set(self::ATTR_NAME, $seed);

        return $seed;
    }

    public function getGeneratedSeedIfAny(Request $request): ?string
    {
        $attr = $request->attributes->get(self::ATTR_NAME);
        return is_string($attr) && $attr !== '' ? $attr : null;
    }
}

