<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\SubTicket;
use App\Entity\Ticket;
use App\Entity\User;
use App\Service\Validation\SubTicketManager;
use PHPUnit\Framework\TestCase;

final class SubTicketManagerTest extends TestCase
{
    private SubTicketManager $manager;

    protected function setUp(): void
    {
        $this->manager = new SubTicketManager();
    }

    private function makeValidSubTicket(): SubTicket
    {
        $s = new SubTicket();
        $s->setMessage('Message de test');
        $s->setTicket(new Ticket());
        $s->setSender(new User());
        $s->setSenderRole('CLIENT');
        return $s;
    }

    public function testValidSubTicket(): void
    {
        $this->assertTrue($this->manager->validate($this->makeValidSubTicket()));
    }

    public function testSubTicketWithoutMessage(): void
    {
        $s = $this->makeValidSubTicket();
        $s->setMessage('');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Message obligatoire.');
        $this->manager->validate($s);
    }

    public function testSubTicketWithoutTicket(): void
    {
        $s = new SubTicket();
        $s->setMessage('Msg');
        $s->setSender(new User());
        $s->setSenderRole('CLIENT');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ticket obligatoire.');
        $this->manager->validate($s);
    }
}
