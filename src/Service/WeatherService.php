<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class WeatherService
{
    private const BASE_URL = 'https://api.openweathermap.org/data/2.5/weather';
    private const TIMEOUT = 5;

    private array $cache = [];

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
    ) {}

    /**
     * Get weather data for a location name.
     * Returns associative array or null on failure.
     */
    public function getWeatherByLocation(string $locationName): ?array
    {
        $cacheKey = strtolower(trim($locationName));
        if (isset($this->cache[$cacheKey])) {
            $cached = $this->cache[$cacheKey];
            if ((time() - $cached['_fetched_at']) < 1800) { // 30-min TTL
                return $cached;
            }
        }

        if (empty($this->apiKey) || $this->apiKey === 'your_openweathermap_key_here') {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', self::BASE_URL, [
                'query' => [
                    'q'     => $locationName,
                    'appid' => $this->apiKey,
                    'units' => 'metric',
                    'lang'  => 'en',
                ],
                'timeout' => self::TIMEOUT,
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = $response->toArray();
            $result = $this->parseResponse($data, $locationName);
            if ($result) {
                $result['_fetched_at'] = time();
                $this->cache[$cacheKey] = $result;
            }
            return $result;

        } catch (\Throwable $e) {
            return null;
        }
    }

    private function parseResponse(array $data, string $requestedLocation): ?array
    {
        $weather = $data['weather'][0] ?? null;
        $main = $data['main'] ?? null;
        if (!$weather || !$main) return null;

        $weatherMain = $weather['main'] ?? 'Unknown';

        return [
            'locationName'       => $data['name'] ?? $requestedLocation,
            'temperature'        => $main['temp'] ?? 0,
            'feelsLike'          => $main['feels_like'] ?? $main['temp'] ?? 0,
            'humidity'           => $main['humidity'] ?? 0,
            'weatherMain'        => $weatherMain,
            'weatherDescription' => $weather['description'] ?? '',
            'iconCode'           => $weather['icon'] ?? '01d',
            'iconUrl'            => 'https://openweathermap.org/img/wn/' . ($weather['icon'] ?? '01d') . '@2x.png',
            'emoji'              => $this->getEmoji($weatherMain, $weather['icon'] ?? ''),
            'formattedTemp'      => round($main['temp'] ?? 0) . '°C',
        ];
    }

    private function getEmoji(string $weatherMain, string $iconCode): string
    {
        return match (strtolower($weatherMain)) {
            'clear'        => str_ends_with($iconCode, 'n') ? '🌙' : '☀️',
            'clouds'       => '☁️',
            'rain', 'drizzle' => '🌧️',
            'thunderstorm' => '⛈️',
            'snow'         => '❄️',
            'mist', 'fog', 'haze' => '🌫️',
            default        => '🌡️',
        };
    }
}
