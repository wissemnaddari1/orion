<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Conversation;
use App\Entity\ConversationMessage;
use App\Entity\User;
use App\Service\Validation\ConversationMessageManager;
use PHPUnit\Framework\TestCase;

final class ConversationMessageManagerTest extends TestCase
{
    private ConversationMessageManager $manager;

    protected function setUp(): void
    {
        $this->manager = new ConversationMessageManager();
    }

    private function makeValidConversationMessage(): ConversationMessage
    {
        $m = new ConversationMessage();
        $m->setContent('Message de test');
        $m->setConversation(new Conversation());
        $m->setSender(new User());
        return $m;
    }

    public function testValidConversationMessage(): void
    {
        $this->assertTrue($this->manager->validate($this->makeValidConversationMessage()));
    }

    public function testConversationMessageWithoutContent(): void
    {
        $m = $this->makeValidConversationMessage();
        $m->setContent('');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Contenu du message obligatoire.');
        $this->manager->validate($m);
    }

    public function testConversationMessageWithoutConversation(): void
    {
        $m = new ConversationMessage();
        $m->setContent('Msg');
        $m->setSender(new User());
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Conversation obligatoire.');
        $this->manager->validate($m);
    }
}
