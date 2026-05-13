<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Psr\Log\LoggerInterface;

class CvParserService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $aiServiceUrl,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Parse CV file and extract profile data using AI service
     * 
     * @param string $filePath Full path to the CV file
     * @param bool $collect Whether to save to AI dataset
     * @return array Extracted profile data
     */
    public function parseCV(string $filePath, bool $collect = false): array
    {
        try {
            $file = new File($filePath);
            
            // Prepare multipart form data
            $formData = new FormDataPart([
                'file' => DataPart::fromPath($filePath),
            ]);

            // Send file to AI service
            $response = $this->httpClient->request('POST', $this->aiServiceUrl . '/parse-cv', [
                'query' => [
                    'collect' => $collect ? 'true' : 'false',
                ],
                'headers' => array_merge(
                    $formData->getPreparedHeaders()->toArray(),
                    ['Accept' => 'application/json']
                ),
                'body' => $formData->bodyToIterable(),
                'timeout' => 30,
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('AI service returned non-200 status', [
                    'status' => $response->getStatusCode(),
                    'file' => $filePath,
                ]);
                return $this->getEmptyResult();
            }

            $data = $response->toArray();
            
            return [
                'success' => true,
                'data' => [
                    'title' => $data['title'] ?? '',
                    'bio' => $data['bio'] ?? '',
                    'experience_years' => $data['experience_years'] ?? null,
                    'hourly_rate' => $data['hourly_rate'] ?? '',
                    'location' => $data['location'] ?? '',
                    'skills' => $data['skills'] ?? [],
                    'phoneNumber' => $data['phoneNumber'] ?? '',
                    'email' => $data['email'] ?? '',
                ],
                'confidence' => $data['confidence'] ?? 0,
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('CV parsing failed', [
                'error' => $e->getMessage(),
                'file' => $filePath,
            ]);
            
            return $this->getEmptyResult();
        }
    }

    /**
     * Get empty result structure for fallback
     */
    private function getEmptyResult(): array
    {
        return [
            'success' => false,
            'data' => [
                'title' => '',
                'bio' => '',
                'experience_years' => null,
                'hourly_rate' => '',
                'location' => '',
                'skills' => [],
                'phoneNumber' => '',
                'email' => '',
            ],
            'confidence' => 0,
            'error' => 'AI service unavailable or parsing failed',
        ];
    }
}
