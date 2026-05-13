<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Service\Validation\NotificationManager;
use PHPUnit\Framework\TestCase;

final class NotificationManagerTest extends TestCase
{
    private NotificationManager $manager;

    protected function setUp(): void
    {
        $this->manager = new NotificationManager();
    }

    private function makeValidNotification(): Notification
    {
        $n = new Notification();
        $n->setUser(new User());
        $n->setType(Notification::TYPE_OFFER_RECEIVED);
        $n->setTitle('Nouvelle offre reçue');
        return $n;
    }

    public function testValidNotification(): void
    {
        $this->assertTrue($this->manager->validate($this->makeValidNotification()));
    }

    public function testNotificationWithoutUser(): void
    {
        $n = new Notification();
        $n->setUser(null);
        $n->setType(Notification::TYPE_OFFER_RECEIVED);
        $n->setTitle('Titre');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Utilisateur obligatoire.');
        $this->manager->validate($n);
    }

    public function testNotificationWithoutTitle(): void
    {
        $n = $this->makeValidNotification();
        $n->setTitle('');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Titre de la notification obligatoire.');
        $this->manager->validate($n);
    }

    public function testNotificationWithInvalidType(): void
    {
        $n = $this->makeValidNotification();
        $n->setType('UNKNOWN_TYPE');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Type de notification invalide.');
        $this->manager->validate($n);
    }
}
