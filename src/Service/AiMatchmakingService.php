<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AiRecommendation;
use App\Entity\ServiceRequest;
use App\Entity\User;
use App\Repository\ServiceRequestRepository;
use App\Repository\UserRepository;
use App\Repository\WorkerProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class AiMatchmakingService implements MatchmakingRecommendationProviderInterface
{
    private const TOP_K = 10;

    public function __construct(
        private readonly ServiceRequestRepository $serviceRequestRepository,
        private readonly WorkerProfileRepository $workerProfileRepository,
        private readonly UserRepository $userRepository,
        private readonly MatchmakingClient $matchmakingClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @return list<array{user: User, profile: \App\Entity\WorkerProfile, score: float, explanations: list<string>}>
     */
    public function getRecommendationsForService(int $serviceId, ?int $userId = null, array $context = []): array
    {
        $serviceRequest = $this->serviceRequestRepository->find($serviceId);
        if (!$serviceRequest instanceof ServiceRequest) {
            return [];
        }

        return $this->getRecommendationsForServiceRequest($serviceRequest, $userId, $context);
    }

    /**
     * @param array<string, mixed> $context
     * @return list<array{user: User, profile: \App\Entity\WorkerProfile, score: float, explanations: list<string>}>
     */
    public function getRecommendationsForServiceRequest(ServiceRequest $serviceRequest, ?int $userId = null, array $context = []): array
    {
        if ($serviceRequest->getCategory() === null || $serviceRequest->getId() === null) {
            return [];
        }

        $cacheKey = sprintf(
            'ai_reco_%d_%d_%s',
            $serviceRequest->getId(),
            $userId ?? 0,
            md5((string) json_encode($context))
        );

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($serviceRequest, $context, $userId): array {
            $item->expiresAfter(300);
            $recommendations = $this->computeRecommendations($serviceRequest, $context);
            $requestedBy = ($userId !== null && $userId > 0)
                ? $this->entityManager->getReference(User::class, $userId)
                : null;
            $this->persistRecommendationsSnapshot($serviceRequest, $requestedBy, $recommendations, $context);
            return $recommendations;
        });
    }

    /**
     * @param array<string, mixed> $context
     * @return list<array{user: User, profile: \App\Entity\WorkerProfile, score: float, explanations: list<string>}>
     */
    private function computeRecommendations(ServiceRequest $serviceRequest, array $context): array
    {
        $profiles = $this->workerProfileRepository->findByCategory($serviceRequest->getCategory());
        if ($profiles === []) {
            return [];
        }

        $userIds = array_values(array_filter(array_map(static fn ($profile) => $profile->getUser()?->getId(), $profiles)));
        if ($userIds === []) {
            return [];
        }

        $users = $this->userRepository->findBy(['id' => $userIds]);
        $usersById = [];
        foreach ($users as $user) {
            $usersById[$user->getId()] = $user;
        }

        $candidates = [];
        foreach ($profiles as $profile) {
            $uid = $profile->getUser()?->getId();
            if ($uid === null || !isset($usersById[$uid])) {
                continue;
            }

            $candidates[$uid] = [
                'profile' => $profile,
                'user' => $usersById[$uid],
            ];
        }

        if ($candidates === []) {
            return [];
        }

        $scores = $this->matchmakingClient->rank($serviceRequest, $candidates, self::TOP_K);
        if ($scores === []) {
            return [];
        }

        $result = [];
        foreach ($scores as $freelancerId => $score) {
            if (!isset($candidates[$freelancerId])) {
                continue;
            }

            /** @var User $user */
            $user = $candidates[$freelancerId]['user'];
            /** @var \App\Entity\WorkerProfile $profile */
            $profile = $candidates[$freelancerId]['profile'];
            $result[] = [
                'user' => $user,
                'profile' => $profile,
                'score' => $score,
                'explanations' => $this->buildExplanations($serviceRequest, $user, $profile, $score, $context),
            ];
        }

        return $result;
    }

    /**
     * @param list<array{user: User, profile: \App\Entity\WorkerProfile, score: float, explanations: list<string>}> $recommendations
     * @param array<string, mixed> $context
     */
    private function persistRecommendationsSnapshot(
        ServiceRequest $serviceRequest,
        ?User $requestedBy,
        array $recommendations,
        array $context
    ): void {
        $qb = $this->entityManager->createQueryBuilder()
            ->delete(AiRecommendation::class, 'r')
            ->where('r.serviceRequest = :serviceRequest')
            ->setParameter('serviceRequest', $serviceRequest);

        if ($requestedBy !== null) {
            $qb->andWhere('r.requestedBy = :requestedBy')
                ->setParameter('requestedBy', $requestedBy);
        } else {
            $qb->andWhere('r.requestedBy IS NULL');
        }

        $qb->getQuery()->execute();

        foreach ($recommendations as $item) {
            $rec = new AiRecommendation();
            $rec->setServiceRequest($serviceRequest);
            $rec->setRequestedBy($requestedBy);
            $rec->setRecommendedUser($item['user']);
            $rec->setScore($item['score']);
            $rec->setExplanations($item['explanations']);
            $rec->setContext($context);
            $this->entityManager->persist($rec);
        }

        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed> $context
     * @return list<string>
     */
    private function buildExplanations(
        ServiceRequest $serviceRequest,
        User $user,
        \App\Entity\WorkerProfile $profile,
        float $score,
        array $context
    ): array {
        $out = [];
        $out[] = sprintf('Match score %.0f%% based on category, budget, and profile fit.', $score * 100);

        if ($serviceRequest->getCategory() !== null && $profile->getWorkerCategory() !== null) {
            if ($serviceRequest->getCategory()->getId() === $profile->getWorkerCategory()->getId()) {
                $out[] = 'Worker category exactly matches the requested service category.';
            }
        }

        if ($user->getRatingAvg() !== null) {
            $out[] = sprintf(
                'Worker rating %.2f with %d reviews.',
                (float) $user->getRatingAvg(),
                (int) ($user->getTotalReviews() ?? 0)
            );
        }

        if (isset($context['stage']) && $context['stage'] === 'pre_negotiation') {
            $out[] = 'Recommended before negotiation to increase acceptance probability.';
        }

        return $out;
    }
}
