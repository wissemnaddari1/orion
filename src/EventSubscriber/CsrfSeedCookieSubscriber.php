<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Security\Csrf\RequestCsrfSeedProvider;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class CsrfSeedCookieSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly RequestCsrfSeedProvider $seedProvider)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        // If the cookie is already present, do nothing.
        $existing = $request->cookies->get(RequestCsrfSeedProvider::COOKIE_NAME);
        if (is_string($existing) && $existing !== '') {
            return;
        }

        $seed = $this->seedProvider->getGeneratedSeedIfAny($request);
        if ($seed === null) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->setCookie(
            Cookie::create(RequestCsrfSeedProvider::COOKIE_NAME)
                ->withValue($seed)
                ->withPath('/')
                ->withSecure($request->isSecure())
                ->withHttpOnly(true)
                ->withSameSite(Cookie::SAMESITE_LAX)
        );
    }
}

