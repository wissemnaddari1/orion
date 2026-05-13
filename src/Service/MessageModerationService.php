<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Message Moderation Service
 * Uses Groq AI to detect toxic/inappropriate content in ticket messages.
 * Fail-open: if the API is down, messages are allowed through.
 */
class MessageModerationService
{
    private const GROQ_API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const ALLOWED_SEVERITIES = ['LOW', 'MEDIUM', 'HIGH'];

    public function __construct(private
        HttpClientInterface $httpClient, private
        LoggerInterface $logger, private
        string $apiKey, private
        string $model = 'llama-3.3-70b-versatile',
        )
    {
    }

    /**
     * Check if a message contains toxic or inappropriate content.
     *
     * @return array{is_toxic: bool, severity: string, reason: string}
     */
    public function moderateMessage(string $message): array
    {
        $safe = ['is_toxic' => false, 'severity' => 'NONE', 'reason' => ''];

        if (empty(trim($message))) {
            return $safe;
        }

        if (empty($this->apiKey)) {
            $this->logger->warning('MessageModeration: Groq API key not configured, skipping moderation.');
            return $safe;
        }

        $prompt = sprintf(
            "You are a content moderation system for a professional support ticket platform.\n\n" .
            "Analyze the following user message for toxic, abusive, hateful, threatening, or inappropriate content.\n" .
            "This includes insults, profanity, slurs, harassment, threats, and any language considered unprofessional or harmful.\n\n" .
            "Message to analyze:\n\"\"\"%s\"\"\"\n\n" .
            "Return ONLY raw JSON, no markdown, no code fences, no explanation.\n" .
            "Return a JSON object with exactly these keys:\n" .
            "- \"is_toxic\": boolean (true if the message contains any inappropriate content)\n" .
            "- \"severity\": one of \"LOW\", \"MEDIUM\", \"HIGH\" (LOW = mild profanity, MEDIUM = insults/harassment, HIGH = threats/slurs/hate speech). If not toxic, use \"LOW\".\n" .
            "- \"reason\": a short explanation of why the message was flagged (empty string if not toxic)\n\n" .
            "Return ONLY the raw JSON object.",
            $message
        );

        try {
            $response = $this->httpClient->request('POST', self::GROQ_API_URL, [
                'timeout' => 15,
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
                    'temperature' => 0.1,
                ],
            ]);

            if (200 !== $response->getStatusCode()) {
                $this->logger->warning('MessageModeration: Groq API returned non-200.', [
                    'status' => $response->getStatusCode(),
                ]);
                return $safe; // fail-open
            }

            $payload = $response->toArray(false);
            $rawText = $payload['choices'][0]['message']['content'] ?? null;

            if (!\is_string($rawText) || '' === trim($rawText)) {
                $this->logger->warning('MessageModeration: Groq returned empty response.');
                return $safe;
            }

            return $this->parseModerationJson(trim($rawText)) ?? $safe;
        }
        catch (TransportExceptionInterface $e) {
            $this->logger->warning('MessageModeration: Groq transport error.', ['error' => $e->getMessage()]);
            return $safe;
        }
        catch (\Throwable $e) {
            $this->logger->warning('MessageModeration: unexpected error.', ['error' => $e->getMessage()]);
            return $safe;
        }
    }

    private function parseModerationJson(string $rawText): ?array
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
            $this->logger->warning('MessageModeration: JSON parse failed.', ['raw' => mb_substr($rawText, 0, 500)]);
            return null;
        }

        $isToxic = (bool)($decoded['is_toxic'] ?? false);
        $severity = strtoupper((string)($decoded['severity'] ?? 'LOW'));
        $reason = trim((string)($decoded['reason'] ?? ''));

        if (!\in_array($severity, self::ALLOWED_SEVERITIES, true)) {
            $severity = 'LOW';
        }

        return [
            'is_toxic' => $isToxic,
            'severity' => $severity,
            'reason' => mb_substr($reason, 0, 500),
        ];
    }
}
