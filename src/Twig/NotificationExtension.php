<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\NotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class NotificationExtension extends AbstractExtension
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('notification_unread_count', [$this, 'unreadCount']),
        ];
    }

    public function unreadCount(): int
    {
        $user = $this->security->getUser();
        if ($user === null) {
            return 0;
        }
        return $this->notificationRepository->countUnreadOfferOnlyByUser($user);
    }
}
