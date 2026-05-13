<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\ServiceRequest;
use App\Entity\ServiceRequirement;
use App\Service\Validation\ServiceRequirementManager;
use PHPUnit\Framework\TestCase;

final class ServiceRequirementManagerTest extends TestCase
{
    private ServiceRequirementManager $manager;

    protected function setUp(): void
    {
        $this->manager = new ServiceRequirementManager();
    }

    private function makeValidServiceRequirement(): ServiceRequirement
    {
        $r = new ServiceRequirement();
        $r->setTitle('API REST');
        $r->setDetails('Fournir une API REST documentée.');
        $r->setService(new ServiceRequest());
        $r->setRequirementType('technical');
        $r->setAnswerFormat('text');
        $r->setPriorityLevel(1);
        return $r;
    }

    public function testValidServiceRequirement(): void
    {
        $this->assertTrue($this->manager->validate($this->makeValidServiceRequirement()));
    }

    public function testServiceRequirementWithoutTitle(): void
    {
        $r = $this->makeValidServiceRequirement();
        $r->setTitle('');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Titre de l\'exigence obligatoire.');
        $this->manager->validate($r);
    }

    public function testServiceRequirementWithoutDetails(): void
    {
        $r = $this->makeValidServiceRequirement();
        $r->setDetails('');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Détails de l\'exigence obligatoires.');
        $this->manager->validate($r);
    }

    public function testServiceRequirementWithNegativePriority(): void
    {
        $r = $this->makeValidServiceRequirement();
        $r->setPriorityLevel(-1);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le niveau de priorité doit être positif ou nul.');
        $this->manager->validate($r);
    }
}
