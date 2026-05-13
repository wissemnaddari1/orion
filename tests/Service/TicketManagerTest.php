<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\CategoryTicket;
use App\Entity\Ticket;
use App\Service\Validation\TicketManager;
use PHPUnit\Framework\TestCase;

final class TicketManagerTest extends TestCase
{
    private TicketManager $manager;

    protected function setUp(): void
    {
        $this->manager = new TicketManager();
    }

    private function makeValidTicket(): Ticket
    {
        $t = new Ticket();
        $t->setSubject('Problème de connexion');
        $t->setStatus('OPEN');
        $t->setPriority('NORMAL');
        $t->setCategory(new CategoryTicket());
        return $t;
    }

    public function testValidTicket(): void
    {
        $this->assertTrue($this->manager->validate($this->makeValidTicket()));
    }

    public function testTicketWithoutSubject(): void
    {
        $t = $this->makeValidTicket();
        $t->setSubject('');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Sujet du ticket obligatoire.');
        $this->manager->validate($t);
    }

    public function testTicketWithoutCategory(): void
    {
        $t = new Ticket();
        $t->setSubject('Sujet');
        $t->setStatus('OPEN');
        $t->setPriority('NORMAL');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Catégorie du ticket obligatoire.');
        $this->manager->validate($t);
    }

    public function testTicketWithInvalidStatus(): void
    {
        $t = $this->makeValidTicket();
        $t->setStatus('INVALID');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Statut du ticket invalide.');
        $this->manager->validate($t);
    }
}
