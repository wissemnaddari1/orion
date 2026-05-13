<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\BanService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * On each request: auto-unban if ban expired; redirect banned users to /banned (except /banned and /logout).
 */
class BanSubscriber implements EventSubscriberInterface
{
    private const ALLOWED_PATHS = ['/banned', '/logout', '/api/logout'];

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private BanService $banService,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 8],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        foreach (self::ALLOWED_PATHS as $allowed) {
            if (str_starts_with($path, $allowed) || $path === rtrim($allowed, '/')) {
                return;
            }
        }

        $token = $this->tokenStorage->getToken();
        if ($token === null || !$token->isAuthenticated()) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        $this->banService->autoUnbanIfExpired($user);

        if (!$user->isBanned()) {
            return;
        }

        if (str_starts_with($path, '/api') || str_contains($request->headers->get('Accept', ''), 'application/json')) {
            $event->setResponse(new JsonResponse([
                'error' => 'account_banned',
                'message' => 'Your account has been restricted. Please contact support.',
            ], Response::HTTP_FORBIDDEN));
            return;
        }

        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate('app_banned_show', [], UrlGeneratorInterface::ABSOLUTE_URL),
            Response::HTTP_FOUND
        ));
    }
}
