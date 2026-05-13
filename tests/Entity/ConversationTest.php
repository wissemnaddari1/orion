<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Contract;
use App\Entity\Conversation;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class ConversationTest extends TestCase
{
    public function testIsClosedDerivedFromContract(): void
    {
        $contract = new Contract();
        $contract->setStatus(Contract::STATUS_ACTIVE);
        $contract->setClientSigned(true);
        $contract->setWorkerSigned(true);

        $client = new User();
        $client->setUsername('c1');
        $worker = new User();
        $worker->setUsername('w1');

        $conv = new Conversation();
        $conv->setContract($contract);
        $conv->setClient($client);
        $conv->setWorker($worker);

        self::assertFalse($conv->isClosed());

        $contract->setStatus(Contract::STATUS_COMPLETED);
        self::assertTrue($conv->isClosed());

        $contract->setStatus(Contract::STATUS_CANCELLED);
        self::assertTrue($conv->isClosed());
    }

    public function testIsDeletedBySetsCorrectTimestamp(): void
    {
        $contract = new Contract();
        $client = new User();
        $client->setUsername('c1');
        $worker = new User();
        $worker->setUsername('w1');

        $conv = new Conversation();
        $conv->setContract($contract);
        $conv->setClient($client);
        $conv->setWorker($worker);

        self::assertFalse($conv->isDeletedBy($client));
        $conv->markDeletedBy($client);
        self::assertNotNull($conv->getDeletedByClientAt());
        self::assertTrue($conv->isDeletedBy($client));
    }

    public function testGetOtherParticipant(): void
    {
        $client = new User();
        $client->setUsername('client');
        $worker = new User();
        $worker->setUsername('worker');
        $this->setUserId($client, 1);
        $this->setUserId($worker, 2);

        $conv = new Conversation();
        $conv->setClient($client);
        $conv->setWorker($worker);

        self::assertNotNull($conv->getOtherParticipant($client));
        self::assertSame('worker', $conv->getOtherParticipant($client)->getUsername());
        self::assertNotNull($conv->getOtherParticipant($worker));
        self::assertSame('client', $conv->getOtherParticipant($worker)->getUsername());
    }

    private function setUserId(User $user, int $id): void
    {
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($user, $id);
    }
}
