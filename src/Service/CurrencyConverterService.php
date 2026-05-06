<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Psr\Log\LoggerInterface;

class CurrencyConverterService
{
    private $httpClient;
    private $cache;
    private $apiKey;
    private $logger;

    public function __construct(
        HttpClientInterface $httpClient,
        CacheInterface $cache,
        string $exchangeRateApiKey,
        LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->cache = $cache;
        $this->apiKey = $exchangeRateApiKey;
        $this->logger = $logger;
    }

    /**
     * Fetches rates from the API or returns cached/fallback rates.
     */
    public function getRates(): array
    {
        return $this->cache->get('currency_rates_tnd', function (ItemInterface $item) {
            $item->expiresAfter(3600 * 6); // Cache for 6 hours

            try {
                $response = $this->httpClient->request('GET', "https://v6.exchangerate-api.com/v6/{$this->apiKey}/latest/TND");
                $data = $response->toArray();

                if (isset($data['result']) && $data['result'] === 'success') {
                    return $data['conversion_rates'];
                }
                
                $this->logger->warning('Currency API returned non-success response: ' . json_encode($data));
            } catch (\Exception $e) {
                $this->logger->error('Currency API error: ' . $e->getMessage());
            }

            // Fallback rates if API fails
            return [
                'TND' => 1,
                'USD' => 0.32,
                'EUR' => 0.30,
                'GBP' => 0.25
            ];
        });
    }

    /**
     * Converts an amount from TND to target currency.
     */
    public function convert(float $amount, string $targetCurrency = 'TND'): float
    {
        $rates = $this->getRates();
        $rate = $rates[strtoupper($targetCurrency)] ?? 1;
        
        return $amount * $rate;
    }

    /**
     * Formats the currency symbol based on code.
     */
    public function getSymbol(string $currencyCode): string
    {
        return match (strtoupper($currencyCode)) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => 'TND',
        };
    }
}
