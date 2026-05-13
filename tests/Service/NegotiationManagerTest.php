<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Negotiation;
use App\Entity\Offer;
use App\Entity\User;
use App\Service\Validation\NegotiationManager;
use PHPUnit\Framework\TestCase;

final class NegotiationManagerTest extends TestCase
{
    private NegotiationManager $manager;

    protected function setUp(): void
    {
        $this->manager = new NegotiationManager();
    }

    private function makeValidNegotiation(): Negotiation
    {
        $n = new Negotiation();
        $n->setOffer(new Offer());
        $n->setOpenedBy(new User());
        $n->setStatus('OPEN');
        return $n;
    }

    public function testValidNegotiation(): void
    {
        $this->assertTrue($this->manager->validate($this->makeValidNegotiation()));
    }

    public function testNegotiationWithoutOffer(): void
    {
        $n = new Negotiation();
        $n->setOpenedBy(new User());
        $n->setStatus('OPEN');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Offre obligatoire.');
        $this->manager->validate($n);
    }

    public function testNegotiationWithNegativeCounterPrice(): void
    {
        $n = $this->makeValidNegotiation();
        $n->setCounterPrice('-50');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le contre-prix ne peut pas être négatif.');
        $this->manager->validate($n);
    }
}
