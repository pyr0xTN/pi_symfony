<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class WeatherService
{
    private const BASE_URL = 'https://api.openweathermap.org/data/2.5/weather';
    private const FORECAST_URL = 'https://api.openweathermap.org/data/2.5/forecast';
    private const TIMEOUT = 5;
    private const CACHE_TTL_SECONDS = 1800;
    private const FORECAST_MAX_AHEAD_SECONDS = 5 * 24 * 3600;

    private array $currentWeatherCache = [];
    private array $forecastCache = [];

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
        if (isset($this->currentWeatherCache[$cacheKey])) {
            $cached = $this->currentWeatherCache[$cacheKey];
            if ((time() - $cached['_fetched_at']) < self::CACHE_TTL_SECONDS) {
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
                $this->currentWeatherCache[$cacheKey] = $result;
            }
            return $result;

        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get forecast for a location at a specific activity date/time.
     * OpenWeather forecast covers about 5 days in 3-hour steps.
     */
    public function getForecastByLocationAndDate(string $locationName, \DateTimeInterface $activityDate): ?array
    {
        if (empty($this->apiKey) || $this->apiKey === 'your_openweathermap_key_here') {
            return null;
        }

        $now = time();
        $targetTs = $activityDate->getTimestamp();

        if ($targetTs < ($now - 3600) || $targetTs > ($now + self::FORECAST_MAX_AHEAD_SECONDS)) {
            return null;
        }

        $forecastData = $this->getForecastDataByLocation($locationName);
        if ($forecastData === null) {
            return null;
        }

        $bestEntry = null;
        $smallestDelta = PHP_INT_MAX;

        foreach ($forecastData['list'] ?? [] as $entry) {
            $entryTs = (int) ($entry['dt'] ?? 0);
            if ($entryTs <= 0) {
                continue;
            }

            $delta = abs($entryTs - $targetTs);
            if ($delta < $smallestDelta) {
                $smallestDelta = $delta;
                $bestEntry = $entry;
            }
        }

        if (!is_array($bestEntry)) {
            return null;
        }

        $cityName = (string) (($forecastData['city']['name'] ?? '') ?: $locationName);
        return $this->parseForecastEntry($bestEntry, $cityName);
    }

    private function getForecastDataByLocation(string $locationName): ?array
    {
        $cacheKey = strtolower(trim($locationName));
        if (isset($this->forecastCache[$cacheKey])) {
            $cached = $this->forecastCache[$cacheKey];
            if ((time() - (int) ($cached['_fetched_at'] ?? 0)) < self::CACHE_TTL_SECONDS) {
                return $cached;
            }
        }

        try {
            $response = $this->httpClient->request('GET', self::FORECAST_URL, [
                'query' => [
                    'q' => $locationName,
                    'appid' => $this->apiKey,
                    'units' => 'metric',
                    'lang' => 'en',
                ],
                'timeout' => self::TIMEOUT,
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = $response->toArray();
            if (!is_array($data) || !isset($data['list']) || !is_array($data['list'])) {
                return null;
            }

            $data['_fetched_at'] = time();
            $this->forecastCache[$cacheKey] = $data;

            return $data;
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseForecastEntry(array $entry, string $locationName): ?array
    {
        $weather = $entry['weather'][0] ?? null;
        $main = $entry['main'] ?? null;
        if (!is_array($weather) || !is_array($main)) {
            return null;
        }

        $weatherMain = (string) ($weather['main'] ?? 'Unknown');
        $iconCode = (string) ($weather['icon'] ?? '01d');

        return [
            'locationName' => $locationName,
            'temperature' => $main['temp'] ?? 0,
            'feelsLike' => $main['feels_like'] ?? $main['temp'] ?? 0,
            'humidity' => $main['humidity'] ?? 0,
            'weatherMain' => $weatherMain,
            'weatherDescription' => $weather['description'] ?? '',
            'iconCode' => $iconCode,
            'iconUrl' => 'https://openweathermap.org/img/wn/' . $iconCode . '@2x.png',
            'emoji' => $this->getEmoji($weatherMain, $iconCode),
            'formattedTemp' => round((float) ($main['temp'] ?? 0)) . '°C',
            'forecastAt' => (int) ($entry['dt'] ?? 0),
        ];
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
