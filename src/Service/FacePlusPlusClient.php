<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class FacePlusPlusClient
{
    private string $apiKey;
    private string $apiSecret;
    private string $baseUrl;

    public function __construct(
        HttpClientInterface $httpClient,
        string $apiKey,
        string $apiSecret,
        string $baseUrl = 'https://api-us.faceplusplus.com/facepp/v3'
    ) {
        $this->httpClient = $httpClient;
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    private HttpClientInterface $httpClient;

    public function detect(string $imageBase64): array
    {
        return $this->request('detect', [
            'image_base64' => $imageBase64,
            'return_attributes' => 'none',
        ]);
    }

    public function compare(string $imageBase64A, string $imageBase64B): array
    {
        return $this->request('compare', [
            'image_base64_1' => $imageBase64A,
            'image_base64_2' => $imageBase64B,
        ]);
    }

    private function request(string $endpoint, array $payload): array
    {
        $response = $this->httpClient->request('POST', $this->baseUrl . '/' . $endpoint, [
            'body' => array_merge($payload, [
                'api_key' => $this->apiKey,
                'api_secret' => $this->apiSecret,
            ]),
        ]);

        $data = $response->toArray(false);

        if (!empty($data['error_message'])) {
            throw new \RuntimeException($data['error_message']);
        }

        return $data;
    }
}
