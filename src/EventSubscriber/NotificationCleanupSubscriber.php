<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Repository\NotificationRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Runs old-notification cleanup once per day (after response is sent).
 * Deletes read notifications older than 30 days and any notification older than 90 days.
 * Disable by setting NOTIFICATION_CLEANUP_ENABLED=0 in env.
 */
final class NotificationCleanupSubscriber implements EventSubscriberInterface
{
    private const CACHE_KEY = 'notification_cleanup_last_run';
    private const CACHE_TTL = 86400; // 24 hours
    private const DAYS_READ = 30;
    private const DAYS_ALL = 90;

    public function __construct(
        private NotificationRepository $notificationRepository,
        private CacheInterface $cache,
        private bool $enabled = true,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => ['onKernelTerminate', -256],
        ];
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->enabled) {
            return;
        }

        try {
            $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) {
                $item->expiresAfter(self::CACHE_TTL);
                $beforeRead = (new \DateTimeImmutable())->modify('-'.self::DAYS_READ.' days');
                $beforeAll = (new \DateTimeImmutable())->modify('-'.self::DAYS_ALL.' days');
                $this->notificationRepository->deleteReadOlderThan($beforeRead);
                $this->notificationRepository->deleteAllOlderThan($beforeAll);
                return (string) time();
            });
        } catch (\Throwable $e) {
            // Do not break the app if cleanup fails (e.g. DB issue)
        }
    }
}
