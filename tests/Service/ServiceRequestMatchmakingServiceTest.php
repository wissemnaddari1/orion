<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Offer;
use App\Entity\ServiceRequest;
use App\Entity\User;
use App\Entity\WorkerCategory;
use App\Service\MatchmakingRecommendationProviderInterface;
use App\Service\NotificationService;
use App\Service\ServiceRequestMatchmakingService;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class ServiceRequestMatchmakingServiceTest extends TestCase
{
    public function testRunMatchmakingCreatesPendingOfferFromAiRecommendations(): void
    {
        $client = (new User())
            ->setEmail('client@example.com')
            ->setPassword('x')
            ->setFirstName('Client')
            ->setLastName('Owner');
        $this->setPrivateId($client, 10);

        $worker = (new User())
            ->setEmail('worker@example.com')
            ->setPassword('x')
            ->setFirstName('Worker')
            ->setLastName('One');
        $this->setPrivateId($worker, 20);

        $category = (new WorkerCategory())
            ->setName('Backend Developer')
            ->setDescription('desc')
            ->setStatus('ACTIVE')
            ->setDisplayOrder(1)
            ->setTotalWorkers(1)
            ->setIcon('icon')
            ->setAverageHourlyRate('50.00')
            ->setCreatedAt(new \DateTime())
            ->setUpdateAt(new \DateTime());

        $serviceRequest = (new ServiceRequest())
            ->setClient($client)
            ->setCategory($category)
            ->setTitle('Build API')
            ->setBudgetMin('1000')
            ->setBudgetMax('2000')
            ->setDuration(10);
        $this->setPrivateId($serviceRequest, 99);

        $aiMatchmakingService = $this->createMock(MatchmakingRecommendationProviderInterface::class);
        $aiMatchmakingService->expects(self::once())
            ->method('getRecommendationsForService')
            ->willReturn([
                [
                    'user' => $worker,
                    'profile' => null,
                    'score' => 0.88,
                    'explanations' => ['Strong skill match'],
                ],
            ]);

        $persistedOffers = [];
        $offerRepository = $this->createMock(EntityRepository::class);
        $offerRepository->method('findOneBy')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(Offer::class)->willReturn($offerRepository);
        $entityManager->expects(self::atLeastOnce())
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persistedOffers): void {
                if ($entity instanceof Offer) {
                    $ref = new \ReflectionObject($entity);
                    $prop = $ref->getProperty('id');
                    $prop->setAccessible(true);
                    if ($prop->getValue($entity) === null) {
                        $prop->setValue($entity, 501);
                    }
                    $persistedOffers[] = $entity;
                }
            });
        $entityManager->expects(self::exactly(2))->method('flush');

        $notificationRepository = $this->createMock(NotificationRepository::class);
        $notificationService = new NotificationService($notificationRepository, $entityManager);

        $service = new ServiceRequestMatchmakingService(
            $aiMatchmakingService,
            $notificationService,
            $entityManager
        );

        $service->runMatchmaking($serviceRequest);

        self::assertCount(1, $persistedOffers);
        self::assertSame(Offer::STATUS_PENDING, $persistedOffers[0]->getStatus());
        self::assertSame(0.88, $persistedOffers[0]->getMatchScore());
    }

    private function setPrivateId(object $entity, int $id): void
    {
        $ref = new \ReflectionObject($entity);
        $prop = $ref->getProperty('id');
        $prop->setAccessible(true);
        $prop->setValue($entity, $id);
    }
}

