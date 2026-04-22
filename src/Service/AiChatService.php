<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiChatService
{
    private const API_URL    = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL      = 'llama-3.3-70b-versatile';
    private const MAX_TOKENS = 600;
    private const TIMEOUT    = 20;

    private const SYSTEM_PROMPT =
        'You are Rihla, a friendly and knowledgeable AI travel assistant for Rehletna — ' .
        'a Tunisian travel community platform. Your tone is warm, enthusiastic, and concise. ' .
        'You specialise in Tunisia (Tunis, Djerba, Sidi Bou Said, Sahara, Sousse, Carthage, ' .
        'Kairouan, Matmata, Tozeur, Tabarka, Hammamet) but handle all global travel questions confidently. ' .
        'Rules: ' .
        '(1) Keep replies concise: 3 to 6 sentences or a short bullet list. ' .
        '(2) Be specific: name real places, real dishes, practical tips. ' .
        '(3) For Tunisia questions, mention the best season to visit and one hidden gem. ' .
        '(4) For itinerary requests, structure clearly by day with suggested times. ' .
        '(5) End every reply with one short friendly follow-up question. ' .
        '(6) Never refuse a travel question. If asked something off-topic, answer briefly then steer back to travel.';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
    ) {}

    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && str_starts_with($this->apiKey, 'gsk_');
    }

    /**
     * Send conversation history and get assistant reply.
     * @param array<array{role: string, content: string}> $history
     */
    public function chat(array $history): string
    {
        if (!$this->isConfigured()) {
            return "⚠ AI assistant not configured yet.\n\nAdd your Groq key to .env:\n  GROQ_API_KEY=gsk_...\n\nGet a free key at console.groq.com";
        }

        $messages = [['role' => 'system', 'content' => self::SYSTEM_PROMPT]];
        foreach ($history as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'json' => [
                    'model'       => self::MODEL,
                    'messages'    => $messages,
                    'max_tokens'  => self::MAX_TOKENS,
                    'temperature' => 0.8,
                ],
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => self::TIMEOUT,
            ]);

            $code = $response->getStatusCode();

            if ($code === 200) {
                $data = $response->toArray();
                return trim($data['choices'][0]['message']['content'] ?? '');
            }
            if ($code === 401) return '⚠ Invalid Groq API key. Please update GROQ_API_KEY in .env.';
            if ($code === 429) return '⚠ Rate limit reached. Please wait a moment and try again.';
            if ($code === 503) return '⚠ Groq is temporarily unavailable. Please try again shortly.';

            return '⚠ Unexpected error (HTTP ' . $code . '). Please try again.';

        } catch (\Throwable $e) {
            return 'Sorry, I couldn\'t reach the AI service right now. Please check your connection and try again.';
        }
    }
}
