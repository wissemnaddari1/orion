<?php

namespace App\Service;

use App\Entity\ServiceRequest;
use App\Entity\User;
use App\Entity\WorkerProfile;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

/**
 * Calls the Matchmaking AI API (POST /rank) to get top-k freelancers for a service request.
 * Uses request + candidate payloads as defined in ai_matchmaking/api.py.
 */
final class MatchmakingClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
    ) {
    }

    /**
     * Rank candidates for the given service request and return top-k by score.
     *
     * @param ServiceRequest $serviceRequest The client's service request
     * @param array<int, array{profile: WorkerProfile, user: User}> $candidates Each key is user_id (freelancer_id), value has profile + user
     * @param int $topK Max number of freelancers to return (default 10)
     * @return array<int, float> Map of freelancer user_id => score (0–1). Empty on API error or no candidates.
     */
    public function rank(ServiceRequest $serviceRequest, array $candidates, int $topK = 10): array
    {
        if ($candidates === []) {
            return [];
        }

        $requestPayload = $this->buildRequestPayload($serviceRequest);
        $candidatePayloads = $this->buildCandidatePayloads($serviceRequest, $candidates);

        $body = [
            'request' => $requestPayload,
            'candidates' => $candidatePayloads,
            'top_k' => $topK,
        ];

        try {
            $response = $this->httpClient->request('POST', $this->baseUrl . '/rank', [
                'json' => $body,
                'timeout' => 10,
            ]);
            $data = $response->toArray();
        } catch (ExceptionInterface $e) {
            return [];
        }

        $results = $data['results'] ?? [];
        $map = [];
        foreach ($results as $item) {
            $map[(int) $item['freelancer_id']] = (float) $item['score'];
        }
        return $map;
    }

    /**
     * Check if the matchmaking API is reachable.
     */
    public function health(): bool
    {
        try {
            $response = $this->httpClient->request('GET', $this->baseUrl . '/health', ['timeout' => 3]);
            return $response->getStatusCode() === 200;
        } catch (ExceptionInterface $e) {
            return false;
        }
    }

    private function buildRequestPayload(ServiceRequest $request): array
    {
        $client = $request->getClient();
        $category = $request->getCategory();
        $budgetUsd = $this->averageBudget($request->getBudgetMin(), $request->getBudgetMax());
        $deadlineDays = $request->getDuration() ?? 7;
        $requiredSkills = $this->requestRequiredSkills($request);

        return [
            'request_id' => $request->getId(),
            'request_category' => $category ? $category->getName() : '',
            'request_budget_usd' => $budgetUsd,
            'request_deadline_days' => $deadlineDays,
            'request_complexity_1to5' => 3,
            'request_language' => 'English',
            'request_timezone' => $this->formatTimezoneForApi($client?->getTimezone()),
            'request_required_skills' => $requiredSkills,
        ];
    }

    /**
     * @param array<int, array{profile: WorkerProfile, user: User}> $candidates
     * @return list<array<string, mixed>>
     */
    private function buildCandidatePayloads(ServiceRequest $serviceRequest, array $candidates): array
    {
        $requestCategory = $serviceRequest->getCategory()?->getName() ?? '';
        $requestLanguage = 'English';
        $requestTimezone = $this->formatTimezoneForApi($serviceRequest->getClient()?->getTimezone());
        $payloads = [];

        foreach ($candidates as $userId => $item) {
            /** @var WorkerProfile $profile */
            /** @var User $user */
            $profile = $item['profile'];
            $user = $item['user'];
            $category = $profile->getWorkerCategory();
            $categoryName = $category ? $category->getName() : '';
            $payloads[] = [
                'freelancer_id' => $user->getId(),
                'freelancer_primary_category' => $categoryName,
                'freelancer_level' => $this->experienceToLevel($profile->getExperienceYears() ?? 0),
                'freelancer_hourly_rate' => (float) ($profile->getHourlyRate() ?? 0),
                'freelancer_rating_avg' => (float) ($user->getRatingAvg() ?? 0),
                'freelancer_total_reviews' => $user->getTotalReviews() ?? 0,
                'freelancer_completed_jobs' => 0,
                'freelancer_response_rate' => 1.0,
                'freelancer_language' => 'English',
                'freelancer_timezone' => $this->formatTimezoneForApi($user->getTimezone()),
                'freelancer_skills' => '',
                'skill_overlap_count' => 0,
                'category_match' => $categoryName !== '' && $categoryName === $requestCategory ? 1 : 0,
                'language_match' => 1,
                'timezone_match' => $this->formatTimezoneForApi($user->getTimezone()) === $requestTimezone ? 1 : 0,
            ];
        }
        return $payloads;
    }

    private function averageBudget(?string $min, ?string $max): float
    {
        $a = (float) ($min ?? 0);
        $b = (float) ($max ?? 0);
        if ($a <= 0 && $b <= 0) {
            return 0.0;
        }
        if ($a <= 0) {
            return $b;
        }
        if ($b <= 0) {
            return $a;
        }
        return ($a + $b) / 2;
    }

    private function requestRequiredSkills(ServiceRequest $request): string
    {
        $titles = [];
        foreach ($request->getRequirements() as $req) {
            $t = $req->getTitle();
            if ($t !== null && $t !== '') {
                $titles[] = $t;
            }
        }
        return implode('|', $titles);
    }

    private function formatTimezoneForApi(?string $timezone): string
    {
        if ($timezone === null || $timezone === '') {
            return 'UTC+1';
        }
        if (stripos($timezone, 'Tunis') !== false || stripos($timezone, 'Africa') !== false) {
            return 'UTC+1';
        }
        if (stripos($timezone, 'America/New_York') !== false) {
            return 'UTC-5';
        }
        if (stripos($timezone, 'America/Los_Angeles') !== false) {
            return 'UTC-8';
        }
        if (stripos($timezone, 'Europe/London') !== false) {
            return 'UTC+0';
        }
        return 'UTC+1';
    }

    private function experienceToLevel(int $years): string
    {
        if ($years >= 9) {
            return 'Expert';
        }
        if ($years >= 6) {
            return 'Senior';
        }
        if ($years >= 3) {
            return 'Mid';
        }
        return 'Junior';
    }
}
