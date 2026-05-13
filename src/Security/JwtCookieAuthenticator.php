<?php

declare(strict_types=1);

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Exception\ExpiredTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidPayloadException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
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
 * JWT authenticator for web (Twig) and API: reads token from Authorization: Bearer or AUTH_TOKEN cookie,
 * validates with Lexik, loads user by identifier (email). On failure redirects to /login with flash.
 */
class JwtCookieAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private const AUTH_COOKIE_NAME = 'AUTH_TOKEN';

    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
        private UserProviderInterface $userProvider,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function supports(Request $request): ?bool
    {
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
            new UserBadge($userIdentifier, fn (string $identifier) => $this->userProvider->loadUserByIdentifier($identifier))
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->getFlashBag()->add('error', 'Please login again.');
        $response = new RedirectResponse($this->urlGenerator->generate('app_login'));
        $this->clearAuthCookie($request, $response);
        return $response;
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        if ($request->hasSession()) {
            $request->getSession()->getFlashBag()->add('error', 'Please login again.');
        }
        $response = new RedirectResponse($this->urlGenerator->generate('app_login'));
        $this->clearAuthCookie($request, $response);
        return $response;
    }

    /**
     * Clear AUTH_TOKEN cookie so the next request to /login is not sent with an invalid token (stops redirect loop).
     */
    private function clearAuthCookie(Request $request, RedirectResponse $response): void
    {
        $response->headers->setCookie(Cookie::create(self::AUTH_COOKIE_NAME)
            ->withValue('')
            ->withExpires(1)
            ->withPath('/')
            ->withSecure($request->isSecure())
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX));
    }

    /**
     * Extract JWT from Authorization: Bearer header or AUTH_TOKEN cookie.
     */
    private function extractToken(Request $request): ?string
    {
        $auth = $request->headers->get('Authorization');
        if ($auth !== null && str_starts_with($auth, 'Bearer ')) {
            return trim(substr($auth, 7));
        }

        $cookie = $request->cookies->get(self::AUTH_COOKIE_NAME);
        if ($cookie !== null && $cookie !== '') {
            return $cookie;
        }

        return null;
    }
}
