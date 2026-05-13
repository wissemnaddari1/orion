<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Contract;
use App\Entity\Milestone;
use App\Service\Validation\MilestoneManager;
use PHPUnit\Framework\TestCase;

final class MilestoneManagerTest extends TestCase
{
    private MilestoneManager $manager;

    protected function setUp(): void
    {
        $this->manager = new MilestoneManager();
    }

    private function makeValidMilestone(): Milestone
    {
        $m = new Milestone();
        $m->setTitle('Phase 1 - Backend API');
        $m->setContract(new Contract());
        $m->setOrderIndex(1);
        $m->setStatus(Milestone::STATUS_PENDING);
        $m->setDueDate(new \DateTimeImmutable('2025-02-01'));
        return $m;
    }

    public function testValidMilestone(): void
    {
        $this->assertTrue($this->manager->validate($this->makeValidMilestone()));
    }

    public function testMilestoneWithoutTitle(): void
    {
        $m = $this->makeValidMilestone();
        $m->setTitle('');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Titre du jalon obligatoire.');
        $this->manager->validate($m);
    }

    public function testMilestoneWithoutContract(): void
    {
        $m = $this->makeValidMilestone();
        $m->setContract(null);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Contrat obligatoire.');
        $this->manager->validate($m);
    }

    public function testMilestoneWithNegativeOrderIndex(): void
    {
        $m = $this->makeValidMilestone();
        $m->setOrderIndex(-1);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'index d\'ordre doit être positif ou nul.');
        $this->manager->validate($m);
    }

    public function testMilestoneWithNegativeAmount(): void
    {
        $m = $this->makeValidMilestone();
        $m->setAmount('-50');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le montant du jalon ne peut pas être négatif.');
        $this->manager->validate($m);
    }
}
