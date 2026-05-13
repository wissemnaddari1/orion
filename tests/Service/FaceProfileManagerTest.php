<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\FaceProfile;
use App\Entity\User;
use App\Service\Validation\FaceProfileManager;
use PHPUnit\Framework\TestCase;

final class FaceProfileManagerTest extends TestCase
{
    private FaceProfileManager $manager;

    protected function setUp(): void
    {
        $this->manager = new FaceProfileManager();
    }

    private function makeValidFaceProfile(): FaceProfile
    {
        $fp = new FaceProfile();
        $fp->setUser(new User());
        $fp->setEmbedding([0.1, -0.2, 0.3]);
        return $fp;
    }

    public function testValidFaceProfile(): void
    {
        $this->assertTrue($this->manager->validate($this->makeValidFaceProfile()));
    }

    public function testFaceProfileWithoutUser(): void
    {
        $fp = new FaceProfile();
        $fp->setUser(null);
        $fp->setEmbedding([0.1]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Utilisateur obligatoire pour le profil facial.');
        $this->manager->validate($fp);
    }

    public function testFaceProfileWithEmptyEmbedding(): void
    {
        $fp = $this->makeValidFaceProfile();
        $fp->setEmbedding([]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'embedding facial ne peut pas être vide.');
        $this->manager->validate($fp);
    }
}
