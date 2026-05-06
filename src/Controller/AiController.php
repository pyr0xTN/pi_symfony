<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiController extends AbstractController
{
    public function __construct(
        private HttpClientInterface $httpClient,
        // The parameter is bound via configuration in services.yaml
        private string $geminiApiKey
    ) {}

    #[Route('/api/gemini/generate-description', name: 'api_gemini_generate', methods: ['POST'])]
    public function generateDescription(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $name = $data['name'] ?? '';
        $type = $data['type'] ?? 'hotel';

        if (empty($name)) {
            return $this->json(['error' => 'Le nom est requis pour générer une description.'], 400);
        }

        if (empty($this->geminiApiKey)) {
            return $this->json(['error' => 'La clé d\'API Gemini n\'est pas configurée dans le fichier .env.'], 500);
        }

        $prompt = sprintf(
            "Tu es un rédacteur pour une agence de voyage prestigieuse. Rédige une description très courte (2 ou 3 phrases maximum) et très attrayante pour %s nommé '%s'. Ne retourne que la description, pas de formatage Markdown, pas d'emojis, et aucune salutation.",
            $type === 'vol' ? 'le vol' : 'l\'hôtel',
            $name
        );

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ]
        ];

        try {
            $response = $this->httpClient->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent', [
                'query' => ['key' => $this->geminiApiKey],
                'json' => $payload,
            ]);

            $result = $response->toArray();
            
            $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if (!$text) {
                return $this->json(['error' => 'Réponse invalide ou vide de l\'IA'], 500);
            }

            return $this->json(['description' => trim($text)]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur de connexion à Gemini: ' . $e->getMessage()], 500);
        }
    }
}
