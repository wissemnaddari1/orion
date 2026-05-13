<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\WorkerCategory;
use App\Service\Validation\WorkerCategoryManager;
use PHPUnit\Framework\TestCase;

final class WorkerCategoryManagerTest extends TestCase
{
    private WorkerCategoryManager $manager;

    protected function setUp(): void
    {
        $this->manager = new WorkerCategoryManager();
    }

    private function makeValidWorkerCategory(): WorkerCategory
    {
        $c = new WorkerCategory();
        $c->setName('Backend Developer');
        $c->setDescription('Développement backend et API.');
        $c->setStatus('active');
        $c->setDisplayOrder(1);
        $c->setTotalWorkers(0);
        $c->setIcon('code');
        $c->setAverageHourlyRate('50.00');
        return $c;
    }

    public function testValidWorkerCategory(): void
    {
        $this->assertTrue($this->manager->validate($this->makeValidWorkerCategory()));
    }

    public function testWorkerCategoryWithoutName(): void
    {
        $c = $this->makeValidWorkerCategory();
        $c->setName('');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Nom de la catégorie obligatoire.');
        $this->manager->validate($c);
    }

    public function testWorkerCategoryWithoutDescription(): void
    {
        $c = $this->makeValidWorkerCategory();
        $c->setDescription('');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Description de la catégorie obligatoire.');
        $this->manager->validate($c);
    }

    public function testWorkerCategoryWithNegativeDisplayOrder(): void
    {
        $c = $this->makeValidWorkerCategory();
        $c->setDisplayOrder(-1);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'ordre d\'affichage doit être positif ou nul.');
        $this->manager->validate($c);
    }

    public function testWorkerCategoryWithNegativeHourlyRate(): void
    {
        $c = $this->makeValidWorkerCategory();
        $c->setAverageHourlyRate('-10');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le taux horaire moyen ne peut pas être négatif.');
        $this->manager->validate($c);
    }
}
