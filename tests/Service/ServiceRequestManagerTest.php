<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\ServiceRequest;
use App\Entity\User;
use App\Entity\WorkerCategory;
use App\Service\Validation\ServiceRequestManager;
use PHPUnit\Framework\TestCase;

final class ServiceRequestManagerTest extends TestCase
{
    private ServiceRequestManager $manager;

    protected function setUp(): void
    {
        $this->manager = new ServiceRequestManager();
    }

    private function makeValidServiceRequest(): ServiceRequest
    {
        $sr = new ServiceRequest();
        $sr->setTitle('Build a REST API');
        $sr->setClient(new User());
        $sr->setCategory(new WorkerCategory());
        $sr->setBudgetMin('100.00');
        $sr->setBudgetMax('500.00');
        $sr->setDuration(14);
        return $sr;
    }

    public function testValidServiceRequest(): void
    {
        $this->assertTrue($this->manager->validate($this->makeValidServiceRequest()));
    }

    public function testServiceRequestWithoutTitle(): void
    {
        $sr = $this->makeValidServiceRequest();
        $sr->setTitle('');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Titre obligatoire.');
        $this->manager->validate($sr);
    }

    public function testServiceRequestWithTitleTooShort(): void
    {
        $sr = $this->makeValidServiceRequest();
        $sr->setTitle('Abc');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre doit faire au moins 5 caractères.');
        $this->manager->validate($sr);
    }

    public function testServiceRequestWithoutClient(): void
    {
        $sr = $this->makeValidServiceRequest();
        $sr->setClient(null);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Client obligatoire.');
        $this->manager->validate($sr);
    }

    public function testServiceRequestWithoutCategory(): void
    {
        $sr = $this->makeValidServiceRequest();
        $sr->setCategory(null);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Catégorie obligatoire.');
        $this->manager->validate($sr);
    }

    public function testServiceRequestWithBudgetMinGreaterThanMax(): void
    {
        $sr = $this->makeValidServiceRequest();
        $sr->setBudgetMin('1000.00');
        $sr->setBudgetMax('500.00');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le budget minimum ne peut pas dépasser le budget maximum.');
        $this->manager->validate($sr);
    }

    public function testServiceRequestWithInvalidDuration(): void
    {
        $sr = $this->makeValidServiceRequest();
        $sr->setDuration(0);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La durée doit être strictement positive.');
        $this->manager->validate($sr);
    }
}
