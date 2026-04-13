<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class SentimentService
{
    private const API_URL    = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL      = 'llama-3.3-70b-versatile';
    private const MAX_TOKENS = 60;
    private const TIMEOUT    = 10;

    private const SYSTEM_PROMPT =
        'You are a sentiment analysis engine. Given a travel post text, respond with ONLY a JSON object ' .
        'containing two fields: "mood" (one of: happy, adventurous, relaxing, romantic, nostalgic, excited, reflective, foodie) ' .
        'and "emoji" (a single emoji that best represents that mood). ' .
        'Do NOT include any other text, explanation, or markdown. Just the raw JSON object.';

    private array $cache = [];

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
    ) {}

    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && str_starts_with($this->apiKey, 'gsk_');
    }

    /**
     * Analyze the sentiment/mood of a post's content.
     * Returns ['mood' => string, 'emoji' => string] or null on failure.
     */
    public function analyze(string $content): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $trimmed = mb_substr(trim($content), 0, 500);
        $cacheKey = md5($trimmed);

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'json' => [
                    'model'       => self::MODEL,
                    'messages'    => [
                        ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                        ['role' => 'user', 'content' => $trimmed],
                    ],
                    'max_tokens'  => self::MAX_TOKENS,
                    'temperature' => 0.3,
                ],
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => self::TIMEOUT,
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = $response->toArray();
            $raw = trim($data['choices'][0]['message']['content'] ?? '');

            // Strip markdown code fences if present
            $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
            $raw = preg_replace('/\s*```$/i', '', $raw);

            $parsed = json_decode($raw, true);
            if ($parsed && isset($parsed['mood'], $parsed['emoji'])) {
                $result = [
                    'mood'  => strtolower($parsed['mood']),
                    'emoji' => $parsed['emoji'],
                ];
                $this->cache[$cacheKey] = $result;
                return $result;
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
