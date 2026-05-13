<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Certificate Verification Service
 * Calls Python FastAPI microservice for AI-based verification
 */
class CertificateVerificationService
{
    private string $pythonApiUrl;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CertificateUploadService $uploadService,
        private HttpClientInterface $httpClient,
        private ?LoggerInterface $logger = null,
        string $pythonApiUrl = 'http://127.0.0.1:8001'
    ) {
        $this->pythonApiUrl = $pythonApiUrl;
    }

    /**
     * Verify certificate by calling Python AI service
     */
    public function verifyAndUpdate(User $user): void
    {
        try {
            $filePath = $this->uploadService->getFullPath($user->getCertificatePath());
            
            if (!file_exists($filePath)) {
                throw new \RuntimeException('Certificate file not found');
            }

            $this->logger?->info('Starting certificate verification', [
                'user_id' => $user->getId(),
                'file_path' => $user->getCertificatePath(),
            ]);

            // Call Python API
            $response = $this->httpClient->request('POST', $this->pythonApiUrl . '/verify-certificate', [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'body' => [
                    'file' => fopen($filePath, 'r'),
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException('Python service returned error: ' . $response->getStatusCode());
            }

            $result = $response->toArray();

            // Update user with AI results
            $user->setCertificateAiVerdict($result['status'] ?? 'needs_review');
            $user->setCertificateAiScore($result['confidence'] ?? 0);
            $user->setCertificateExtractedText($result['extracted_text'] ?? '');

            $this->entityManager->flush();

            $this->logger?->info('Certificate verification completed', [
                'user_id' => $user->getId(),
                'verdict' => $result['status'],
                'confidence' => $result['confidence'],
            ]);

        } catch (\Exception $e) {
            $this->logger?->error('Certificate verification failed', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            // Set fallback values
            $user->setCertificateAiVerdict('needs_review');
            $user->setCertificateAiScore(0);
            $user->setCertificateExtractedText('Verification failed: ' . $e->getMessage());
            
            $this->entityManager->flush();
        }
    }

    /**
     * Check if Python AI service is available
     */
    public function isPythonServiceAvailable(): bool
    {
        try {
            $response = $this->httpClient->request('GET', $this->pythonApiUrl . '/health', [
                'timeout' => 2,
            ]);
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }
}
