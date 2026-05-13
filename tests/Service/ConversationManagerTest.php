<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Contract;
use App\Entity\Conversation;
use App\Entity\User;
use App\Service\Validation\ConversationManager;
use PHPUnit\Framework\TestCase;

final class ConversationManagerTest extends TestCase
{
    private ConversationManager $manager;

    protected function setUp(): void
    {
        $this->manager = new ConversationManager();
    }

    private function makeValidConversation(): Conversation
    {
        $c = new Conversation();
        $c->setContract(new Contract());
        $c->setClient(new User());
        $c->setWorker(new User());
        return $c;
    }

    public function testValidConversation(): void
    {
        $this->assertTrue($this->manager->validate($this->makeValidConversation()));
    }

    public function testConversationWithoutContract(): void
    {
        $c = new Conversation();
        $c->setContract(null);
        $c->setClient(new User());
        $c->setWorker(new User());
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Contrat obligatoire.');
        $this->manager->validate($c);
    }

    public function testConversationWithoutClient(): void
    {
        $c = new Conversation();
        $c->setContract(new Contract());
        $c->setClient(null);
        $c->setWorker(new User());
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Client obligatoire.');
        $this->manager->validate($c);
    }
}
