<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Service for interacting with the Ticket Support AI API
 * 
 * This service provides methods to communicate with the AI-powered ticket
 * support system that suggests solutions based on historical tickets.
 */
class TicketSupportAIService
{
    private HttpClientInterface $httpClient;
    private string $apiBaseUrl;
    private int $timeout;

    /**
     * Constructor
     *
     * @param HttpClientInterface $httpClient HTTP client for making API requests
     * @param string $aiApiUrl Base URL of the AI API (from configuration)
     * @param int $timeout Request timeout in seconds (default: 5)
     */
    public function __construct(
        HttpClientInterface $httpClient,
        string $aiApiUrl = 'http://127.0.0.1:8015',
        int $timeout = 60
        )
    {
        $this->httpClient = $httpClient;
        $this->apiBaseUrl = rtrim($aiApiUrl, '/');
        $this->timeout = $timeout;
    }

    /**
     * Request a solution suggestion for a new ticket
     *
     * @param string $subject Ticket subject
     * @param string $message Ticket message (problem description)
     * @param string|null $category Optional ticket category
     * @return array AI response with suggested solution, confidence score, and escalation decision
     * @throws HttpException If the API request fails
     */
    public function solveTicket(string $subject, string $message, ?string $category = null): array
    {
        try {
            error_log('Attempting to call AI API at: ' . $this->apiBaseUrl . '/ai/solve');
            error_log('Request data: subject=' . $subject . ', category=' . $category);

            $response = $this->httpClient->request('POST', $this->apiBaseUrl . '/ai/solve', [
                'json' => [
                    'subject' => $subject,
                    'message' => $message,
                    'category' => $category,
                ],
                'timeout' => $this->timeout,
            ]);

            $statusCode = $response->getStatusCode();
            $contentType = $response->getHeaders(false)['content-type'][0] ?? 'unknown';
            error_log('AI API response status code: ' . $statusCode);
            error_log('AI API response content type: ' . $contentType);

            if ($statusCode !== 200) {
                $content = $response->getContent(false);
                error_log('AI API returned non-200 status code: ' . $statusCode);
                error_log('Response content: ' . $content);
                throw new HttpException(
                    $statusCode,
                    'AI API returned non-200 status code: ' . $statusCode
                    );
            }

            // Check if the response is JSON
            if (strpos($contentType, 'application/json') === false) {
                $content = $response->getContent(false);
                error_log('AI API returned non-JSON response: ' . $contentType);
                error_log('Response content: ' . $content);
                throw new \Exception('AI API returned non-JSON response: ' . $contentType);
            }

            try {
                $result = $response->toArray();
                error_log('AI API response parsed successfully');
                return $result;
            }
            catch (\Exception $e) {
                $content = $response->getContent(false);
                error_log('Failed to parse AI API response as JSON: ' . $e->getMessage());
                error_log('Response content: ' . $content);
                throw $e;
            }
        }
        catch (\Exception $e) {
            // Log the error (in production, use proper logging)
            error_log('Error calling AI API: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());

            // Return a safe response that escalates to admin
            return [
                'suggested_solution' => null,
                'confidence_score' => 0.0,
                'escalate_to_admin' => true,
                'similar_ticket' => null,
                'processed_at' => (new \DateTime())->format('c'),
            ];
        }
    }

    /**
     * Update the AI knowledge base with a new resolved ticket
     *
     * @param string $subject Ticket subject
     * @param string $message Ticket message (problem description)
     * @param string $resolution Admin's resolution for the problem
     * @param string|null $category Optional ticket category
     * @return array API response
     * @throws HttpException If the API request fails
     */
    public function updateKnowledgeBase(
        string $subject,
        string $message,
        string $resolution,
        ?string $category = null
        ): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->apiBaseUrl . '/ai/knowledge-base/update', [
                'json' => [
                    'subject' => $subject,
                    'message' => $message,
                    'resolution' => $resolution,
                    'category' => $category,
                ],
                'timeout' => $this->timeout,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                throw new HttpException(
                    $statusCode,
                    'AI API returned non-200 status code: ' . $statusCode
                    );
            }

            return $response->toArray();
        }
        catch (\Exception $e) {
            // Log the error (in production, use proper logging)
            error_log('Error updating AI knowledge base: ' . $e->getMessage());

            throw new HttpException(
                500,
                'Failed to update AI knowledge base: ' . $e->getMessage()
                );
        }
    }

    /**
     * Request a model retrain
     *
     * Call this method after adding multiple tickets to ensure consistency.
     *
     * @return array API response
     * @throws HttpException If the API request fails
     */
    public function retrainModel(): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->apiBaseUrl . '/ai/knowledge-base/retrain', [
                'timeout' => $this->timeout * 2, // Retraining might take longer
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                throw new HttpException(
                    $statusCode,
                    'AI API returned non-200 status code: ' . $statusCode
                    );
            }

            return $response->toArray();
        }
        catch (\Exception $e) {
            // Log the error (in production, use proper logging)
            error_log('Error retraining AI model: ' . $e->getMessage());

            throw new HttpException(
                500,
                'Failed to retrain AI model: ' . $e->getMessage()
                );
        }
    }

    /**
     * Get statistics about the AI knowledge base
     *
     * @return array Knowledge base statistics
     * @throws HttpException If the API request fails
     */
    public function getKnowledgeBaseStats(): array
    {
        try {
            $response = $this->httpClient->request('GET', $this->apiBaseUrl . '/ai/knowledge-base/stats', [
                'timeout' => $this->timeout,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                throw new HttpException(
                    $statusCode,
                    'AI API returned non-200 status code: ' . $statusCode
                    );
            }

            return $response->toArray();
        }
        catch (\Exception $e) {
            // Log the error (in production, use proper logging)
            error_log('Error getting AI knowledge base stats: ' . $e->getMessage());

            throw new HttpException(
                500,
                'Failed to get AI knowledge base stats: ' . $e->getMessage()
                );
        }
    }

    /**
     * Check if the AI service is available
     *
     * @return bool True if the service is available, false otherwise
     */
    public function isServiceAvailable(): bool
    {
        try {
            $response = $this->httpClient->request('GET', $this->apiBaseUrl . '/health', [
                'timeout' => min(10, $this->timeout),
            ]);

            return $response->getStatusCode() === 200;
        }
        catch (\Exception $e) {
            return false;
        }
    }
}
