<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Service\Validation\UserManager;
use PHPUnit\Framework\TestCase;

final class UserManagerTest extends TestCase
{
    private UserManager $manager;

    protected function setUp(): void
    {
        $this->manager = new UserManager();
    }

    private function makeValidUser(): User
    {
        $user = new User();
        $user->setUsername('johndoe');
        $user->setEmail('john@example.com');
        $user->setPasswordHash('hash');
        $user->setRole(UserRole::CLIENT);
        $user->setStatus(UserStatus::ACTIVE);
        $user->setFirstName('John');
        $user->setLastName('Doe');
        return $user;
    }

    public function testValidUser(): void
    {
        $user = $this->makeValidUser();
        $this->assertTrue($this->manager->validate($user));
    }

    public function testUserWithoutEmail(): void
    {
        $user = $this->makeValidUser();
        $user->setEmail('');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email obligatoire.');
        $this->manager->validate($user);
    }

    public function testUserWithInvalidEmail(): void
    {
        $user = $this->makeValidUser();
        $user->setEmail('not-an-email');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email invalide.');
        $this->manager->validate($user);
    }

    public function testUserWithoutUsername(): void
    {
        $user = $this->makeValidUser();
        $user->setUsername('');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Username obligatoire.');
        $this->manager->validate($user);
    }

    public function testUserWithUsernameTooShort(): void
    {
        $user = $this->makeValidUser();
        $user->setUsername('ab');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Username doit faire entre 3 et 50 caractères.');
        $this->manager->validate($user);
    }

    public function testUserWithInvalidUsernameFormat(): void
    {
        $user = $this->makeValidUser();
        $user->setUsername('john doe!');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Username peut contenir uniquement lettres, chiffres, point, tiret et underscore.');
        $this->manager->validate($user);
    }

    public function testUserWithoutFirstName(): void
    {
        $user = $this->makeValidUser();
        $user->setFirstName('');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Prénom obligatoire.');
        $this->manager->validate($user);
    }

    public function testUserWithoutLastName(): void
    {
        $user = $this->makeValidUser();
        $user->setLastName('');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Nom obligatoire.');
        $this->manager->validate($user);
    }
}
