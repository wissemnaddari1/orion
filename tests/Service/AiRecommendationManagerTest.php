<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\AiRecommendation;
use App\Entity\ServiceRequest;
use App\Entity\User;
use App\Service\Validation\AiRecommendationManager;
use PHPUnit\Framework\TestCase;

final class AiRecommendationManagerTest extends TestCase
{
    private AiRecommendationManager $manager;

    protected function setUp(): void
    {
        $this->manager = new AiRecommendationManager();
    }

    private function makeValidAiRecommendation(): AiRecommendation
    {
        $a = new AiRecommendation();
        $a->setServiceRequest(new ServiceRequest());
        $a->setRecommendedUser(new User());
        $a->setScore(0.85);
        return $a;
    }

    public function testValidAiRecommendation(): void
    {
        $this->assertTrue($this->manager->validate($this->makeValidAiRecommendation()));
    }

    public function testAiRecommendationWithoutServiceRequest(): void
    {
        $a = new AiRecommendation();
        $a->setServiceRequest(null);
        $a->setRecommendedUser(new User());
        $a->setScore(0.5);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Demande de service obligatoire.');
        $this->manager->validate($a);
    }

    public function testAiRecommendationWithScoreOutOfRange(): void
    {
        $a = $this->makeValidAiRecommendation();
        $a->setScore(1.5);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le score doit être entre 0 et 1.');
        $this->manager->validate($a);
    }
}
