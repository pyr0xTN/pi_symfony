<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class PlacesAutocompleteService
{
    private const BASE_URL = 'https://nominatim.openstreetmap.org/search';
    private const USER_AGENT = 'Rehletna.tn/1.0 (Travel Community Platform)';
    private const TIMEOUT = 5;

    private float $lastRequestTime = 0;

    public function __construct(private HttpClientInterface $httpClient) {}

    /**
     * Get autocomplete predictions for a search query.
     * @return array<array{placeId: string, description: string, mainText: string, secondaryText: string}>
     */
    public function getAutocompletePredictions(string $input): array
    {
        if (strlen(trim($input)) < 2) return [];

        $this->enforceRateLimit();

        try {
            $response = $this->httpClient->request('GET', self::BASE_URL, [
                'query' => [
                    'q'              => trim($input),
                    'format'         => 'json',
                    'addressdetails' => 1,
                    'limit'          => 5,
                ],
                'headers' => ['User-Agent' => self::USER_AGENT],
                'timeout' => self::TIMEOUT,
            ]);

            if ($response->getStatusCode() !== 200) return [];

            $data = $response->toArray();
            return $this->parseResponse($data);

        } catch (\Throwable $e) {
            return [];
        }
    }

    private function parseResponse(array $results): array
    {
        $predictions = [];
        foreach ($results as $location) {
            $placeId = (string) ($location['place_id'] ?? 0);
            $displayName = $location['display_name'] ?? '';
            $address = $location['address'] ?? [];

            $mainText = $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['state'] ?? null;
            $secondaryText = $address['country'] ?? '';

            if (!$mainText) {
                $parts = explode(',', $displayName);
                $mainText = trim($parts[0] ?? '');
                $secondaryText = trim(end($parts));
            }

            if ($placeId && $displayName) {
                $predictions[] = [
                    'placeId'       => $placeId,
                    'description'   => $displayName,
                    'mainText'      => $mainText,
                    'secondaryText' => $secondaryText,
                ];
            }
        }
        return $predictions;
    }

    private function enforceRateLimit(): void
    {
        $now = microtime(true);
        $delta = $now - $this->lastRequestTime;
        if ($delta < 1.0) {
            usleep((int) ((1.0 - $delta) * 1_000_000));
        }
        $this->lastRequestTime = microtime(true);
    }
}
