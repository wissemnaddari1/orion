<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ServiceRequest;

interface MatchmakingRecommendationProviderInterface
{
    /**
     * @param array<string, mixed> $context
     * @return list<array{user: \App\Entity\User, profile: mixed, score: float, explanations: list<string>}>
     */
    public function getRecommendationsForService(int $serviceId, ?int $userId = null, array $context = []): array;

    /**
     * @param array<string, mixed> $context
     * @return list<array{user: \App\Entity\User, profile: mixed, score: float, explanations: list<string>}>
     */
    public function getRecommendationsForServiceRequest(ServiceRequest $serviceRequest, ?int $userId = null, array $context = []): array;
}
