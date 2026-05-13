<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Entity\ServiceRequest;

class ServiceRequestScoringAi
{
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL   = 'llama-3.1-8b-instant';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey
    ) {}

    public function scoreServiceRequest(ServiceRequest $service): array
    {
        // Build requirements list
        $requirementsList = '';
        foreach ($service->getRequirements() as $req) {
            $requirementsList .= '- ' . $req->getTitle() . "\n";
        }
        if (empty(trim($requirementsList))) {
            $requirementsList = 'No specific requirements listed.';
        }

        $categoryName = $service->getCategory()?->getName() ?? 'General';

        $prompt = <<<PROMPT
You are an expert freelance project pricing consultant with deep knowledge of market rates.

A client has submitted the following project. Your job is to independently assess what this project ACTUALLY requires — ignore any duration or budget the client may have in mind.

PROJECT DETAILS:
- Title: {$service->getTitle()}
- Category: {$categoryName}
- Description: {$service->getDescription()}
- Requirements:
{$requirementsList}

INSTRUCTIONS:
1. Break down the project into its core technical tasks.
2. Estimate realistic working hours per task based on industry standards for a skilled {$categoryName} freelancer.
3. Sum the hours to get total effort, then convert to calendar days (assume 6 productive hours/day).
4. Price based on fair market hourly rates for a {$categoryName} (not what the client budgeted), Consider complexity, not just hours and price the hours as a reasonable price for a freelancer based on his level 
5. Give a min price (entry/mid rate) and max price (expert rate) for the SAME scope.
6. Try to not exceed 10000 as max price, the platform is not offering that yet 
STRICT RULES:
- A simple project (landing page, basic API): 20–60 hours
- A medium project (full CRUD app, dashboard): 60–150 hours  
- A complex project (real-time features, complex integrations): 150–250 hours


Price = total hours × hourly rate (min rate for min price, max rate for max price)

Respond with ONLY a valid JSON object. No markdown, no explanation, just raw JSON.

{
  "suggested_price_min": <number>,
  "suggested_price_max": <number>,
  "suggested_duration": <realistic integer number of calendar days>,
  "reasoning": "<3-4 sentences: mention the key tasks, total estimated hours, and why this duration and price range is fair>"
}
PROMPT;

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => self::MODEL,
                    'temperature' => 0.3,
                    'max_tokens'  => 300,
                    'messages'    => [
                        [
                            'role'    => 'system',
                            'content' => 'You are a pricing expert. Always respond with valid raw JSON only. Never use markdown code blocks.',
                        ],
                        [
                            'role'    => 'user',
                            'content' => $prompt,
                        ],
                    ],
                ],
            ]);

            $data    = $response->toArray();
            $content = $data['choices'][0]['message']['content'] ?? '';

            // Strip accidental markdown fences just in case
            $content = preg_replace('/```json|```/', '', $content);
            $content = trim($content);

            $parsed = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Llama returned invalid JSON: ' . $content);
            }

            foreach (['suggested_price_min', 'suggested_price_max', 'suggested_duration', 'reasoning'] as $key) {
                if (!array_key_exists($key, $parsed)) {
                    throw new \RuntimeException("Missing key '{$key}' in response");
                }
            }

            return [
                'success'             => true,
                'suggested_price_min' => (float) $parsed['suggested_price_min'],
                'suggested_price_max' => (float) $parsed['suggested_price_max'],
                'suggested_duration'  => (int)   $parsed['suggested_duration'],
                'reasoning'           => (string) $parsed['reasoning'],
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }
}