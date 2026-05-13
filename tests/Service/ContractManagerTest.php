<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Contract;
use App\Entity\User;
use App\Service\Validation\ContractManager;
use PHPUnit\Framework\TestCase;

final class ContractManagerTest extends TestCase
{
    private ContractManager $manager;

    protected function setUp(): void
    {
        $this->manager = new ContractManager();
    }

    private function makeValidContract(): Contract
    {
        $c = new Contract();
        $c->setTitle('Development contract');
        $c->setScope('Build a REST API and admin panel.');
        $c->setClient(new User());
        $c->setWorker(new User());
        $c->setAgreedPrice('5000.00');
        $c->setStartDate(new \DateTimeImmutable('2025-01-01'));
        $c->setEndDate(new \DateTimeImmutable('2025-06-30'));
        $c->setStatus(Contract::STATUS_DRAFT);
        return $c;
    }

    public function testValidContract(): void
    {
        $this->assertTrue($this->manager->validate($this->makeValidContract()));
    }

    public function testContractWithoutTitle(): void
    {
        $c = $this->makeValidContract();
        $c->setTitle('');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Titre du contrat obligatoire.');
        $this->manager->validate($c);
    }

    public function testContractWithoutScope(): void
    {
        $c = $this->makeValidContract();
        $c->setScope('');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Périmètre (scope) obligatoire.');
        $this->manager->validate($c);
    }

    public function testContractWithoutClient(): void
    {
        $c = $this->makeValidContract();
        $c->setClient(null);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Client obligatoire.');
        $this->manager->validate($c);
    }

    public function testContractWithStartAfterEnd(): void
    {
        $c = $this->makeValidContract();
        $c->setStartDate(new \DateTimeImmutable('2025-12-01'));
        $c->setEndDate(new \DateTimeImmutable('2025-06-01'));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de début ne peut pas être après la date de fin.');
        $this->manager->validate($c);
    }

    public function testContractWithNegativePrice(): void
    {
        $c = $this->makeValidContract();
        $c->setAgreedPrice('-100');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prix convenu doit être positif ou nul.');
        $this->manager->validate($c);
    }
}
