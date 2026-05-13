<?php

declare(strict_types=1);

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Exception\ExpiredTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidPayloadException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Stateless JWT authenticator.
 *
 * Token sources:
 * - Authorization: Bearer <jwt>
 * - X-Auth-Token: <jwt> (useful when the frontend reads from localStorage and sets a custom header)
 * - X-Authorization: Bearer <jwt> (alternative header)
 * - AUTH_TOKEN cookie (enables normal browser navigation for Twig pages)
 */
final class JwtAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private const AUTH_COOKIE_NAME = 'AUTH_TOKEN';

    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly UserProviderInterface $userProvider,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        $path = $request->getPathInfo();

        // Skip authentication for public API routes (login, logout, 2FA verify, face-login)
        if (in_array($path, ['/api/login', '/api/logout', '/api/face-login', '/api/auth/2fa/verify'], true)) {
            return false;
        }

        // Don't run JWT on web login/face-login paths so the login page loads without redirect loop
        // when the browser sends a stale or invalid AUTH_TOKEN cookie
        if (str_starts_with($path, '/login') || str_starts_with($path, '/auth/face')) {
            return false;
        }

        return $this->extractToken($request) !== null;
    }

    public function authenticate(Request $request): Passport
    {
        $token = $this->extractToken($request);
        if ($token === null) {
            throw new AuthenticationException('No JWT token found.');
        }

        try {
            $payload = $this->jwtManager->parse($token);
        } catch (JWTDecodeFailureException $e) {
            if ($e->getReason() === JWTDecodeFailureException::EXPIRED_TOKEN) {
                throw new ExpiredTokenException();
            }
            throw new AuthenticationException('Invalid JWT token.', 0, $e);
        }

        $idClaim = $this->jwtManager->getUserIdClaim() ?? 'username';
        if (!isset($payload[$idClaim]) || $payload[$idClaim] === '') {
            throw new InvalidPayloadException($idClaim);
        }

        $userIdentifier = (string) $payload[$idClaim];

        return new SelfValidatingPassport(
            new UserBadge(
                $userIdentifier,
                fn (string $identifier) => $this->userProvider->loadUserByIdentifier($identifier)
            ),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if ($this->isApiRequest($request) || $request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
            return new JsonResponse([
                'error' => 'unauthorized',
                'message' => 'Invalid or expired token.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        if ($this->isApiRequest($request) || $request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
            return new JsonResponse([
                'error' => 'unauthorized',
                'message' => 'Authentication required.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    private function isApiRequest(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), '/api');
    }

    private function extractToken(Request $request): ?string
    {
        // 1. Check Authorization header (Bearer token)
        $auth = $request->headers->get('Authorization');
        if ($auth !== null) {
            $auth = trim($auth);
            if (str_starts_with($auth, 'Bearer ')) {
                $candidate = trim(substr($auth, 7));
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        // 2. Check X-Auth-Token header
        $xAuthToken = $request->headers->get('X-Auth-Token');
        if ($xAuthToken !== null) {
            $candidate = trim($xAuthToken);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        // 3. Check X-Authorization header
        $xAuthorization = $request->headers->get('X-Authorization');
        if ($xAuthorization !== null) {
            $xAuthorization = trim($xAuthorization);
            if (str_starts_with($xAuthorization, 'Bearer ')) {
                $candidate = trim(substr($xAuthorization, 7));
                if ($candidate !== '') {
                    return $candidate;
                }
            }
            if ($xAuthorization !== '') {
                return $xAuthorization;
            }
        }

        // 4. Check form data (for regular form submissions)
        $formToken = $request->request->get('_jwt_token');
        if ($formToken !== null) {
            $candidate = trim((string) $formToken);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        // 5. Check cookie (fallback for compatibility)
        $cookie = $request->cookies->get(self::AUTH_COOKIE_NAME);
        if ($cookie !== null) {
            $candidate = trim((string) $cookie);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }
}

