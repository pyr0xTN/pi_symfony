<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class AiSearchService
{
    private HttpClientInterface $httpClient;
    private string $geminiApiKey;

    public function __construct(HttpClientInterface $httpClient, string $geminiApiKey)
    {
        $this->httpClient = $httpClient;
        $this->geminiApiKey = $geminiApiKey;
    }

    public function extractFilters(string $userQuery): ?array
    {
        if (empty($this->geminiApiKey)) {
            // Fallback or handle missing API key
            return null;
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $this->geminiApiKey;

        $prompt = <<<EOT
You are an AI assistant that extracts search criteria for a travel booking platform. 
The platform offers "hotel" and "vol" (flight) services.
Extract the following information from the user query into a strict JSON format. Do not return any other text or markdown, just the JSON.

Expected JSON properties:
- "type": (string) either "hotel", "vol" or null if not explicitly mentioned.
- "localisation": (string) destination city or location or null. For a flight, this might be the arrival city.
- "ville_depart": (string) departure city or null.
- "ville_arrivee": (string) arrival city or null.
- "nombre_etoiles": (integer) hotel stars (e.g. 4) or null.
- "max_prix": (float) maximum price or budget mentioned or null.

User Query: "$userQuery"
EOT;

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'response_mime_type' => 'application/json',
                    ]
                ]
            ]);

            $data = $response->toArray();
            
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $jsonString = $data['candidates'][0]['content']['parts'][0]['text'];
                $decoded = json_decode($jsonString, true);
                return $decoded;
            }

        } catch (\Exception $e) {
            // Handle error silently or log it
            error_log("Gemini API Error: " . $e->getMessage());
        }

        return null;
    }
}
