<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\CategoryTicket;
use App\Service\Validation\CategoryTicketManager;
use PHPUnit\Framework\TestCase;

final class CategoryTicketManagerTest extends TestCase
{
    private CategoryTicketManager $manager;

    protected function setUp(): void
    {
        $this->manager = new CategoryTicketManager();
    }

    private function makeValidCategoryTicket(): CategoryTicket
    {
        $c = new CategoryTicket();
        $c->setName('Support technique');
        return $c;
    }

    public function testValidCategoryTicket(): void
    {
        $this->assertTrue($this->manager->validate($this->makeValidCategoryTicket()));
    }

    public function testCategoryTicketWithoutName(): void
    {
        $c = $this->makeValidCategoryTicket();
        $c->setName('');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Nom de la catégorie obligatoire.');
        $this->manager->validate($c);
    }
}
