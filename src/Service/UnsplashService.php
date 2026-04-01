<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class UnsplashService
{
    private const API_BASE = 'https://api.unsplash.com';
    private const TIMEOUT  = 10;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $accessKey,
    ) {}

    public function isConfigured(): bool
    {
        return !empty($this->accessKey) && $this->accessKey !== 'your_unsplash_key_here';
    }

    /**
     * Fetch a travel photo for the given location query.
     * Returns array with url, photographer info, or null.
     */
    public function fetchPhoto(string $locationQuery): ?array
    {
        if (!$this->isConfigured() || empty(trim($locationQuery))) return null;

        try {
            $response = $this->httpClient->request('GET', self::API_BASE . '/search/photos', [
                'query' => [
                    'query'          => trim($locationQuery) . ' travel',
                    'per_page'       => 5,
                    'orientation'    => 'landscape',
                    'content_filter' => 'high',
                ],
                'headers' => [
                    'Authorization'  => 'Client-ID ' . $this->accessKey,
                    'Accept-Version' => 'v1',
                ],
                'timeout' => self::TIMEOUT,
            ]);

            if ($response->getStatusCode() !== 200) return null;

            $data = $response->toArray();
            $results = $data['results'] ?? [];
            if (empty($results)) return null;

            $photo = $results[0];
            return [
                'url'              => $photo['urls']['regular'] ?? '',
                'photographer'     => $photo['user']['name'] ?? 'Unknown',
                'photographerUrl'  => $photo['user']['links']['html'] ?? '',
                'altDescription'   => $photo['alt_description'] ?? 'Travel photo',
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }
}
