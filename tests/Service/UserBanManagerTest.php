<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Entity\UserBan;
use App\Service\Validation\UserBanManager;
use PHPUnit\Framework\TestCase;

final class UserBanManagerTest extends TestCase
{
    private UserBanManager $manager;

    protected function setUp(): void
    {
        $this->manager = new UserBanManager();
    }

    private function makeValidUserBan(): UserBan
    {
        $ban = new UserBan();
        $ban->setUser(new User());
        $ban->setReason('Violation des conditions d\'utilisation');
        $ban->setType(UserBan::TYPE_TEMP);
        $ref = new \ReflectionClass($ban);
        $prop = $ref->getProperty('endsAt');
        $prop->setAccessible(true);
        $prop->setValue($ban, new \DateTimeImmutable('+1 month'));
        return $ban;
    }

    public function testValidUserBan(): void
    {
        $this->assertTrue($this->manager->validate($this->makeValidUserBan()));
    }

    public function testUserBanWithoutReason(): void
    {
        $ban = $this->makeValidUserBan();
        $ban->setReason('');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Raison du bannissement obligatoire.');
        $this->manager->validate($ban);
    }

    public function testUserBanTempWithoutEndsAt(): void
    {
        $ban = new UserBan();
        $ban->setUser(new User());
        $ban->setReason('Raison');
        $ban->setType(UserBan::TYPE_TEMP);
        $ref = new \ReflectionClass($ban);
        $prop = $ref->getProperty('endsAt');
        $prop->setAccessible(true);
        $prop->setValue($ban, null);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Un bannissement temporaire doit avoir une date de fin.');
        $this->manager->validate($ban);
    }

    public function testUserBanWithEndsAtBeforeBannedAt(): void
    {
        $ban = new UserBan();
        $ban->setUser(new User());
        $ban->setReason('Raison');
        $ban->setType(UserBan::TYPE_TEMP);
        $ref = new \ReflectionClass($ban);
        $endsProp = $ref->getProperty('endsAt');
        $endsProp->setAccessible(true);
        $endsProp->setValue($ban, new \DateTimeImmutable('2025-01-01'));
        $bannedProp = $ref->getProperty('bannedAt');
        $bannedProp->setAccessible(true);
        $bannedProp->setValue($ban, new \DateTimeImmutable('2025-02-01'));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de fin doit être après la date de bannissement.');
        $this->manager->validate($ban);
    }
}
