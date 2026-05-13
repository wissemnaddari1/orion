<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

/**
 * HTTP client for the local Python face recognition service (FastAPI).
 * Reads FACE_SERVICE_URL from env.
 */
final class FaceRecognitionClient
{
    private const TIMEOUT = 15.0;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Check if the face recognition service is reachable.
     */
    public function isAvailable(): bool
    {
        try {
            $response = $this->httpClient->request('GET', $this->baseUrl . '/health', [
                'timeout' => 3.0,
            ]);
            return $response->getStatusCode() === 200;
        } catch (ExceptionInterface $e) {
            return false;
        }
    }

    /**
     * Compute face embedding from base64 image.
     *
     * @return array{faces: int, embedding: list<float>}
     * @throws \InvalidArgumentException on NO_FACE / MULTIPLE_FACES / BAD_IMAGE
     * @throws \RuntimeException on timeout or service error
     */
    public function embed(string $imageBase64): array
    {
        $payload = ['image_base64' => $imageBase64];
        $response = $this->request('POST', '/embed', $payload);

        $data = $response;
        if (!isset($data['faces'], $data['embedding']) || !\is_array($data['embedding'])) {
            throw new \RuntimeException('Invalid embed response from face service.');
        }

        return [
            'faces' => (int) $data['faces'],
            'embedding' => array_map('floatval', $data['embedding']),
        ];
    }

    /**
     * Match image against candidates; returns best match if distance <= threshold.
     *
     * @param list<array{user_id: int, embedding: list<float>}> $candidates
     * @return array{matched: bool, user_id: int|null, distance: float|null, threshold: float}
     */
    public function match(string $imageBase64, array $candidates, float $threshold = 0.6): array
    {
        $payload = [
            'image_base64' => $imageBase64,
            'candidates' => $candidates,
            'threshold' => $threshold,
        ];
        $response = $this->request('POST', '/match', $payload);

        return [
            'matched' => (bool) ($response['matched'] ?? false),
            'user_id' => isset($response['user_id']) ? (int) $response['user_id'] : null,
            'distance' => isset($response['distance']) ? (float) $response['distance'] : null,
            'threshold' => (float) ($response['threshold'] ?? $threshold),
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $body = []): array
    {
        $url = $this->baseUrl . $path;
        try {
            $response = $this->httpClient->request($method, $url, [
                'json' => $body,
                'timeout' => self::TIMEOUT,
            ]);
            $status = $response->getStatusCode();
            $data = $response->toArray(false);

            if ($status === 422 && isset($data['detail'])) {
                $detail = $data['detail'];
                $error = \is_array($detail) ? ($detail['error'] ?? 'UNKNOWN') : 'UNKNOWN';
                $message = \is_array($detail) ? ($detail['message'] ?? (string) $detail) : (string) $detail;
                throw new \InvalidArgumentException($message, 0);
            }

            if ($status >= 400) {
                $message = \is_array($data) && isset($data['detail']) ? (is_array($data['detail']) ? ($data['detail']['message'] ?? json_encode($data['detail'])) : $data['detail']) : 'Face service error.';
                throw new \RuntimeException($message);
            }

            return $data;
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (ExceptionInterface $e) {
            throw new \RuntimeException('Face recognition service unavailable. Please try again.', 0, $e);
        }
    }
}
