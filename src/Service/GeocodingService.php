<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeocodingService
{
    private const BASE_URL = 'https://nominatim.openstreetmap.org/search';
    private const USER_AGENT = 'Rehletna.tn/1.0 (Travel Community Platform)';
    private const TIMEOUT = 6;

    private array $cache = [];
    private float $lastRequestTime = 0;

    public function __construct(private HttpClientInterface $httpClient) {}

    /**
     * Resolve location name to [lat, lon] or null.
     */
    public function geocode(string $placeName): ?array
    {
        $key = strtolower(trim($placeName));
        if (empty($key)) return null;

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $this->enforceRateLimit();

        try {
            $response = $this->httpClient->request('GET', self::BASE_URL, [
                'query' => [
                    'q'      => $placeName,
                    'format' => 'json',
                    'limit'  => 1,
                ],
                'headers' => ['User-Agent' => self::USER_AGENT],
                'timeout' => self::TIMEOUT,
            ]);

            if ($response->getStatusCode() !== 200) return null;

            $data = $response->toArray();
            if (empty($data)) return null;

            $coords = [
                'lat' => (float) $data[0]['lat'],
                'lon' => (float) $data[0]['lon'],
            ];
            $this->cache[$key] = $coords;
            return $coords;

        } catch (\Throwable $e) {
            return null;
        }
    }

    private function enforceRateLimit(): void
    {
        $now = microtime(true);
        $delta = $now - $this->lastRequestTime;
        if ($delta < 1.1) {
            usleep((int) ((1.1 - $delta) * 1_000_000));
        }
        $this->lastRequestTime = microtime(true);
    }
}
