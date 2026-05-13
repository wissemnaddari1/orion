<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TicketAiInsightService
{
    private const GROQ_API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const ALLOWED_SENTIMENTS = ['POSITIVE', 'NEUTRAL', 'NEGATIVE'];
    private const ALLOWED_URGENCY = ['LOW', 'MEDIUM', 'HIGH'];
    private const ALLOWED_PRIORITY = ['LOW', 'MEDIUM', 'HIGH'];

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $apiKey,
        private string $model = 'llama-3.3-70b-versatile',
    ) {
    }

    public function analyzeTicket(string $subject, string $message): ?array
    {
        if (empty($this->apiKey)) {
            $this->logger->warning('Groq API key is not configured.');
            return null;
        }

        $prompt = sprintf(
            "You are a professional customer support ticket analyzer.\n\nAnalyze the following ticket and return ONLY raw JSON, no markdown, no explanation, no code fences, no extra text.\n\nTicket subject: \"%s\"\nTicket message: \"%s\"\n\nReturn a JSON object with exactly these keys:\n- \"sentiment\": one of \"POSITIVE\", \"NEUTRAL\", \"NEGATIVE\"\n- \"urgency\": one of \"LOW\", \"MEDIUM\", \"HIGH\"\n- \"suggested_priority\": one of \"LOW\", \"MEDIUM\", \"HIGH\"\n- \"short_summary\": a brief summary of the ticket\n\nReturn ONLY the raw JSON object. Do not wrap it in markdown code blocks. Do not add any explanation.",
            $subject,
            $message,
        );

        try {
            $response = $this->httpClient->request('POST', self::GROQ_API_URL, [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.2,
                ],
            ]);

            if (200 !== $response->getStatusCode()) {
                $errorBody = trim($response->getContent(false));
                $this->logger->warning('Groq API returned non-200 status.', [
                    'status' => $response->getStatusCode(),
                    'error_body' => mb_substr($errorBody, 0, 1000),
                ]);
                return null;
            }

            $payload = $response->toArray(false);

            // Extract content from OpenAI-compatible response: choices[0].message.content
            $rawText = $payload['choices'][0]['message']['content'] ?? null;

            if (!\is_string($rawText) || '' === trim($rawText)) {
                $this->logger->warning('Groq API returned empty response text.', [
                    'payload' => $payload,
                ]);
                return null;
            }

            $rawText = trim($rawText);

            $parsed = $this->parseInsightJson($rawText);

            if (null === $parsed) {
                $this->logger->warning('Ticket AI insight parse failed: invalid JSON schema.', [
                    'raw_text' => mb_substr($rawText, 0, 1000),
                ]);
                return null;
            }

            return $parsed;
        } catch (TransportExceptionInterface $e) {
            $this->logger->warning('Groq API transport error.', ['error' => $e->getMessage()]);
            return null;
        } catch (\Throwable $e) {
            $this->logger->warning('Groq API unexpected error.', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Parse a free-form paragraph into structured ticket fields using Groq AI.
     *
     * @param string $paragraph  The user's free-form description
     * @param array  $categories Array of ['id' => int, 'name' => string]
     * @return array|null        Parsed fields or null on failure
     */
    public function parseTicketFromParagraph(string $paragraph, array $categories): ?array
    {
        if (empty($this->apiKey)) {
            $this->logger->warning('Groq API key is not configured.');
            return null;
        }

        if (empty(trim($paragraph))) {
            return null;
        }

        // Build category list for the prompt
        $categoryList = implode("\n", array_map(function ($cat) {
            return sprintf('  - id: %d, name: "%s"', $cat['id'], $cat['name']);
        }, $categories));

        $prompt = sprintf(
            "You are a helpful assistant that converts a user's free-form paragraph into a structured support ticket.\n\n" .
            "The user wrote:\n\"\"\"\n%s\n\"\"\"\n\n" .
            "Available ticket categories:\n%s\n\n" .
            "Available priorities: LOW, MEDIUM, HIGH, URGENT\n\n" .
            "Based on the paragraph above, return ONLY raw JSON (no markdown, no code fences, no explanation) with these keys:\n" .
            "- \"subject\": a short, clear ticket subject (max 100 chars)\n" .
            "- \"category_id\": the id of the best matching category from the list above (integer)\n" .
            "- \"priority\": one of LOW, MEDIUM, HIGH, URGENT based on the urgency described\n" .
            "- \"message\": a well-written, professional version of the user's paragraph that clearly describes the issue (keep the original meaning, improve grammar and structure)\n\n" .
            "Return ONLY the raw JSON object.",
            $paragraph,
            $categoryList
        );

        try {
            $response = $this->httpClient->request('POST', self::GROQ_API_URL, [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.3,
                ],
            ]);

            if (200 !== $response->getStatusCode()) {
                $this->logger->warning('Groq API returned non-200 for paragraph parse.', [
                    'status' => $response->getStatusCode(),
                ]);
                return null;
            }

            $payload = $response->toArray(false);
            $rawText = $payload['choices'][0]['message']['content'] ?? null;

            if (!is_string($rawText) || '' === trim($rawText)) {
                $this->logger->warning('Groq returned empty response for paragraph parse.');
                return null;
            }

            $rawText = trim($rawText);

            // Strip markdown fences if present
            if (str_starts_with($rawText, '```')) {
                $rawText = preg_replace('/^```(?:json)?\s*/i', '', $rawText) ?? $rawText;
                $rawText = preg_replace('/\s*```$/', '', $rawText) ?? $rawText;
                $rawText = trim($rawText);
            }

            $decoded = json_decode($rawText, true);
            if (!is_array($decoded)) {
                $this->logger->warning('Groq paragraph parse returned invalid JSON.', [
                    'raw' => mb_substr($rawText, 0, 500),
                ]);
                return null;
            }

            // Validate required fields
            $subject = trim((string)($decoded['subject'] ?? ''));
            $categoryId = (int)($decoded['category_id'] ?? 0);
            $priority = strtoupper(trim((string)($decoded['priority'] ?? 'MEDIUM')));
            $message = trim((string)($decoded['message'] ?? ''));

            if ('' === $subject || '' === $message) {
                return null;
            }

            // Validate priority
            if (!in_array($priority, ['LOW', 'MEDIUM', 'HIGH', 'URGENT'], true)) {
                $priority = 'MEDIUM';
            }

            // Validate category_id exists in the provided list
            $validCatIds = array_column($categories, 'id');
            if (!in_array($categoryId, $validCatIds, true)) {
                $categoryId = $validCatIds[0] ?? 0; // fallback to first category
            }

            return [
                'subject' => mb_substr($subject, 0, 255),
                'category_id' => $categoryId,
                'priority' => $priority,
                'message' => mb_substr($message, 0, 5000),
            ];
        } catch (TransportExceptionInterface $e) {
            $this->logger->warning('Groq API transport error for paragraph parse.', ['error' => $e->getMessage()]);
            return null;
        } catch (\Throwable $e) {
            $this->logger->warning('Groq API unexpected error for paragraph parse.', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function parseInsightJson(string $rawText): ?array
    {
        $candidate = trim($rawText);

        // Strip markdown code fences if model wraps response
        if (str_starts_with($candidate, '```')) {
            $candidate = preg_replace('/^```(?:json)?\s*/i', '', $candidate) ?? $candidate;
            $candidate = preg_replace('/\s*```$/', '', $candidate) ?? $candidate;
            $candidate = trim($candidate);
        }

        $decoded = json_decode($candidate, true);

        if (!\is_array($decoded)) {
            return null;
        }

        $sentiment = strtoupper((string)($decoded['sentiment'] ?? ''));
        $urgency = strtoupper((string)($decoded['urgency'] ?? ''));
        $priority = strtoupper((string)($decoded['suggested_priority'] ?? ''));
        $summary = trim((string)($decoded['short_summary'] ?? ''));

        if (!\in_array($sentiment, self::ALLOWED_SENTIMENTS, true)) {
            return null;
        }

        if (!\in_array($urgency, self::ALLOWED_URGENCY, true)) {
            return null;
        }

        if (!\in_array($priority, self::ALLOWED_PRIORITY, true)) {
            return null;
        }

        if ('' === $summary) {
            return null;
        }

        return [
            'sentiment' => $sentiment,
            'urgency' => $urgency,
            'suggested_priority' => $priority,
            'short_summary' => mb_substr($summary, 0, 500),
        ];
    }
}
