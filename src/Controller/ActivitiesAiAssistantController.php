<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ActivitiesAiAssistantController extends AbstractController
{
    private const GROQ_API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const GROQ_MODEL = 'llama-3.3-70b-versatile';

    #[Route('/activities/ai-assistant/reply', name: 'app_activities_ai_assistant_reply', methods: ['POST'])]
    public function reply(Request $request, HttpClientInterface $httpClient): JsonResponse
    {
        if (!$this->isGranted('ROLE_USER')) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required. Please log in and try again.',
            ], 401);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid JSON payload.',
            ], 400);
        }

        $history = isset($payload['history']) && is_array($payload['history']) ? $payload['history'] : [];
        $formContext = isset($payload['formContext']) && is_array($payload['formContext']) ? $payload['formContext'] : [];

        $title = trim((string) ($formContext['title'] ?? ''));
        $location = trim((string) ($formContext['location'] ?? ''));
        $category = trim((string) ($formContext['category'] ?? ''));

        $contextParts = [];
        if ($title !== '') {
            $contextParts[] = 'Title: "' . $title . '"';
        }
        if ($location !== '') {
            $contextParts[] = 'Location: "' . $location . '"';
        }
        if ($category !== '') {
            $contextParts[] = 'Category: "' . $category . '"';
        }

        $systemPrompt = implode("\n", [
            'You are an AI assistant embedded inside an activity creation form for Rehletna.tn, a Tunisian tourism platform.',
            'Scope restriction: you must answer ONLY topics related to travel, tourism, and activities available on Rehletna.tn.',
            'If the user asks anything outside this scope (coding, politics, health, general trivia, etc.), do not answer it.',
            'For out-of-scope questions, reply with exactly this style: "I can only help with travel and activities on Rehletna.tn. Ask me about destinations, activity ideas, pricing, seasons, or planning."',
            'Your role is to help tour guides fill in their activity form by suggesting:',
            '- Compelling activity titles and descriptions',
            '- Best locations and places to visit in Tunisia',
            '- Ideal seasons and weather for outdoor activities',
            '- Fair pricing based on activity type and duration',
            '- Recommended duration for different activity types',
            '- Popular categories that attract tourists',
            'Keep responses concise (2-4 sentences max unless asked for more).',
            'Never provide answers outside travel/activity scope, even if the user insists.',
            'When you suggest a description or title, end with: [APPLY:fieldname] where fieldname is one of: title, description, location, category.',
            'For example, after suggesting a description, write [APPLY:description] on a new line.',
            'Be friendly, practical, and knowledgeable about Tunisia tourism.',
            $contextParts !== []
                ? 'Current form context - ' . implode(', ', $contextParts) . '.'
                : 'Current form context - no title/location/category entered yet.',
        ]);

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $item) {
            if (!is_array($item)) {
                continue;
            }

            $role = (string) ($item['role'] ?? '');
            $content = trim((string) ($item['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }

            $messages[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        $apiKey = trim((string) ($_ENV['GROQ_API_KEY'] ?? $_SERVER['GROQ_API_KEY'] ?? ''));
        if ($apiKey === '') {
            return $this->json([
                'success' => false,
                'message' => 'GROQ_API_KEY is not configured on the server.',
            ], 503);
        }

        try {
            $response = $httpClient->request('POST', self::GROQ_API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => self::GROQ_MODEL,
                    'messages' => $messages,
                    'max_tokens' => 400,
                    'temperature' => 0.7,
                ],
                'timeout' => 25,
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);

            if ($statusCode >= 400) {
                $errorMessage = 'AI provider error (' . $statusCode . ').';
                if (is_array($data) && isset($data['error']) && is_array($data['error']) && !empty($data['error']['message'])) {
                    $errorMessage = (string) $data['error']['message'];
                }

                return $this->json([
                    'success' => false,
                    'message' => $errorMessage,
                ], $statusCode);
            }

            $reply = '';
            if (isset($data['choices'][0]['message']['content'])) {
                $reply = trim((string) $data['choices'][0]['message']['content']);
            }

            if ($reply === '') {
                return $this->json([
                    'success' => false,
                    'message' => 'AI provider returned an empty response.',
                ], 502);
            }

            return $this->json([
                'success' => true,
                'reply' => $reply,
            ]);
        } catch (TransportExceptionInterface) {
            return $this->json([
                'success' => false,
                'message' => 'Unable to contact the AI provider right now.',
            ], 503);
        } catch (\Throwable) {
            return $this->json([
                'success' => false,
                'message' => 'Unexpected server error while generating AI reply.',
            ], 500);
        }
    }
}
