<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * In production: log all exceptions and return a generic response.
 * Never expose exception messages or stack traces to the UI.
 */
final class ExceptionSubscriber implements EventSubscriberInterface
{
    private const SAFE_MESSAGE = 'An error occurred. Please try again later.';
    private const SAFE_AUTH_MESSAGE = 'Unable to authenticate. Please try again later.';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $environment,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', -128], // run before default error handling
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if ($this->environment === 'dev') {
            return; // Let Symfony show the debug error page in dev
        }

        $throwable = $event->getThrowable();
        $request = $event->getRequest();

        $this->logger->error('Unhandled exception', [
            'exception' => $throwable,
            'message' => $throwable->getMessage(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
        ]);

        $isAuthRelated = $this->isAuthRelatedException($throwable);
        $message = $isAuthRelated ? self::SAFE_AUTH_MESSAGE : self::SAFE_MESSAGE;

        if ($this->isApiRequest($request) || $request->isXmlHttpRequest() || $this->wantsJson($request)) {
            $statusCode = $throwable instanceof HttpException ? $throwable->getStatusCode() : Response::HTTP_INTERNAL_SERVER_ERROR;
            $event->setResponse(new JsonResponse([
                'error' => 'error',
                'message' => $message,
            ], $statusCode));
            $event->allowCustomResponseCode();
            return;
        }

        if ($isAuthRelated) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_login', ['auth_error' => '1'])));
            return;
        }

        $event->setResponse(new Response(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Error</title></head><body><h1>Something went wrong</h1><p>' . htmlspecialchars($message) . '</p></body></html>',
            Response::HTTP_INTERNAL_SERVER_ERROR,
            ['Content-Type' => 'text/html']
        ));
    }

    private function isApiRequest(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), '/api');
    }

    private function wantsJson(Request $request): bool
    {
        return str_contains($request->headers->get('Accept', ''), 'application/json');
    }

    private function isAuthRelatedException(\Throwable $e): bool
    {
        $message = $e->getMessage();
        $class = $e::class;
        $authKeywords = ['JWT', 'jwt', 'token', 'authentication', 'authorization', 'key', 'lexik', 'credentials'];
        foreach ($authKeywords as $keyword) {
            if (str_contains($message, $keyword) || str_contains($class, $keyword)) {
                return true;
            }
        }
        return false;
    }
}
