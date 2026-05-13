<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Entity\WorkerProfile;
use App\Service\Validation\WorkerProfileManager;
use PHPUnit\Framework\TestCase;

final class WorkerProfileManagerTest extends TestCase
{
    private WorkerProfileManager $manager;

    protected function setUp(): void
    {
        $this->manager = new WorkerProfileManager();
    }

    private function makeValidWorkerProfile(): WorkerProfile
    {
        $p = new WorkerProfile();
        $p->setUser(new User());
        $p->setTitle('Développeur PHP');
        $p->setBio('Expérience en Symfony.');
        $p->setHourlyRate('45');
        $p->setExperienceYears(5);
        $p->setLocation('Paris');
        $p->setAvailabilityStatus('available');
        return $p;
    }

    public function testValidWorkerProfile(): void
    {
        $this->assertTrue($this->manager->validate($this->makeValidWorkerProfile()));
    }

    public function testWorkerProfileWithoutTitle(): void
    {
        $p = $this->makeValidWorkerProfile();
        $p->setTitle('');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Titre professionnel obligatoire.');
        $this->manager->validate($p);
    }

    public function testWorkerProfileWithoutBio(): void
    {
        $p = $this->makeValidWorkerProfile();
        $p->setBio('');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bio obligatoire.');
        $this->manager->validate($p);
    }

    public function testWorkerProfileWithNegativeHourlyRate(): void
    {
        $p = $this->makeValidWorkerProfile();
        $p->setHourlyRate('-10');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le taux horaire ne peut pas être négatif.');
        $this->manager->validate($p);
    }
}
