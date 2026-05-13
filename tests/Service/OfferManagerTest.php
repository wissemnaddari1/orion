<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Offer;
use App\Entity\ServiceRequest;
use App\Entity\User;
use App\Service\Validation\OfferManager;
use PHPUnit\Framework\TestCase;

final class OfferManagerTest extends TestCase
{
    private OfferManager $manager;

    protected function setUp(): void
    {
        $this->manager = new OfferManager();
    }

    private function makeValidOffer(): Offer
    {
        $offer = new Offer();
        $offer->setPrice('250.00');
        $offer->setEstimatedTimeDays(7);
        $offer->setStatus(Offer::STATUS_PENDING);
        $offer->setServiceRequest(new ServiceRequest());
        $offer->setWorker(new User());
        return $offer;
    }

    public function testValidOffer(): void
    {
        $this->assertTrue($this->manager->validate($this->makeValidOffer()));
    }

    public function testOfferWithNegativePrice(): void
    {
        $offer = $this->makeValidOffer();
        $offer->setPrice('-10');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prix doit être positif ou nul.');
        $this->manager->validate($offer);
    }

    public function testOfferWithInvalidEstimatedTimeDays(): void
    {
        $offer = $this->makeValidOffer();
        $offer->setEstimatedTimeDays(0);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le délai estimé (jours) doit être strictement positif.');
        $this->manager->validate($offer);
    }

    public function testOfferWithoutServiceRequest(): void
    {
        $offer = new Offer();
        $offer->setPrice('250.00');
        $offer->setEstimatedTimeDays(7);
        $offer->setStatus(Offer::STATUS_PENDING);
        $offer->setWorker(new User());
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Demande de service obligatoire.');
        $this->manager->validate($offer);
    }

    public function testOfferWithoutWorker(): void
    {
        $offer = new Offer();
        $offer->setPrice('250.00');
        $offer->setEstimatedTimeDays(7);
        $offer->setStatus(Offer::STATUS_PENDING);
        $offer->setServiceRequest(new ServiceRequest());
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Worker obligatoire.');
        $this->manager->validate($offer);
    }
}
