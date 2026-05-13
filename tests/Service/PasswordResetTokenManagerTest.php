<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Service\Validation\PasswordResetTokenManager;
use PHPUnit\Framework\TestCase;

final class PasswordResetTokenManagerTest extends TestCase
{
    private PasswordResetTokenManager $manager;

    protected function setUp(): void
    {
        $this->manager = new PasswordResetTokenManager();
    }

    private function makeValidPasswordResetToken(): PasswordResetToken
    {
        return new PasswordResetToken(
            new User(),
            'abc123hash',
            new \DateTimeImmutable('+1 hour')
        );
    }

    public function testValidPasswordResetToken(): void
    {
        $this->assertTrue($this->manager->validate($this->makeValidPasswordResetToken()));
    }

    public function testPasswordResetTokenExpiresBeforeRequested(): void
    {
        $user = new User();
        $requestedAt = new \DateTime('2025-01-01 12:00:00');
        $expiresAt = new \DateTimeImmutable('2025-01-01 11:00:00');
        $token = new PasswordResetToken($user, 'hash', $expiresAt);
        $ref = new \ReflectionClass($token);
        $prop = $ref->getProperty('requestedAt');
        $prop->setAccessible(true);
        $prop->setValue($token, $requestedAt);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date d\'expiration doit être après la date de demande.');
        $this->manager->validate($token);
    }
}
