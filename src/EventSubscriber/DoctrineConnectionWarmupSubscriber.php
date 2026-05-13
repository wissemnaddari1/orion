<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Establishes the Doctrine DB connection at the very start of the request
 * so the ~10s first-connection cost is paid upfront instead of mid-authentication.
 */
final class DoctrineConnectionWarmupSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['warmupConnection', 512],
        ];
    }

    public function warmupConnection(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        // Keep an explicit LIMIT so Doctrine Doctor does not treat this probe as unbounded SELECT.
        $this->connection->fetchOne('SELECT 1 LIMIT 1');
    }
}
