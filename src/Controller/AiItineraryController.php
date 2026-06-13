<?php
namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AiItineraryController extends AbstractController
{
    #[Route('/activities/ai-matcher', name: 'app_ai_itinerary', methods: ['GET'])]
    public function index(): \Symfony\Component\HttpFoundation\Response
    {
        return $this->render('activities/ai_matcher.html.twig');
    }

    #[Route('/activities/ai-matcher/recommend', name: 'app_ai_itinerary_recommend', methods: ['POST'])]
    public function recommend(Request $request, Connection $connection): StreamedResponse
    {
        $user = $this->getUser();
        $budget   = (float) ($request->request->get('budget', 300));
        $duration = (int)   ($request->request->get('duration', 3));
        $interests = $request->request->get('interests', '');
        $weather   = $request->request->get('weather', 'any');
        $groupType = $request->request->get('group_type', 'solo');

        // Load real activities from DB
        $activities = $connection->fetchAllAssociative(
            'SELECT a.idActivite, a.titre, a.description, a.lieu, a.categorie,
                    a.prix, a.placesDisponibles, a.dateActivite, a.dureParJour,
                    COALESCE(COUNT(ach.idAchat), 0) AS bookings
             FROM activite a
             LEFT JOIN achat ach ON ach.idActivite = a.idActivite
             WHERE a.statut = "Actif" AND a.placesDisponibles > 0
             GROUP BY a.idActivite
             ORDER BY bookings DESC
             LIMIT 20'
        );

        $activitiesText = implode("\n", array_map(function ($a) {
            return sprintf(
                '- ID:%d | %s | Location: %s | Category: %s | Price: %s DT | Duration: %s days | Available places: %d',
                $a['idActivite'], $a['titre'], $a['lieu'] ?? '?',
                $a['categorie'] ?? '?', $a['prix'], $a['dureParJour'] ?? 1,
                $a['placesDisponibles']
            );
        }, $activities));

        $userContext = '';
        if ($user instanceof User) {
            $userContext = 'User: ' . $user->getFullName() . ' (' . $user->getUsername() . ')';
        }

        $prompt = <<<PROMPT
You are an expert travel advisor for Rehletna.tn, a Tunisian activity booking platform.

{$userContext}

USER PREFERENCES:
- Budget: {$budget} DT total
- Trip duration: {$duration} days
- Interests: {$interests}
- Weather preference: {$weather}
- Group type: {$groupType}

AVAILABLE ACTIVITIES IN CATALOG:
{$activitiesText}

TASK:
Recommend the 3 best activities from the catalog above that match the user's profile.
For each recommendation output EXACTLY this JSON structure (output only valid JSON, no markdown):
{
  "recommendations": [
    {
      "id": <activity id>,
      "name": "<activity name>",
      "match_score": <integer 0-100>,
      "price": <price as number>,
      "why": "<2 sentences explaining why this matches their interests, budget, duration, and weather preference>",
      "tip": "<one practical tip for this activity>",
      "best_day": <suggested day number in the trip, e.g. 1>
    }
  ],
  "itinerary_summary": "<3 sentences describing the overall trip plan>",
  "total_cost": <sum of recommended activity prices>,
  "remaining_budget": <budget minus total_cost>
}
PROMPT;

        $apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? '';

        $response = new StreamedResponse(function () use ($prompt, $apiKey) {
            $ch = curl_init('https://api.anthropic.com/v1/messages');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'x-api-key: ' . $apiKey,
                    'anthropic-version: 2023-06-01',
                    'content-type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => 'claude-sonnet-4-5',
                    'max_tokens' => 1024,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ]),
            ]);

            $result = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($result, true);
            $text = $data['content'][0]['text'] ?? '{}';

            // Clean markdown fences if present
            $text = preg_replace('/^```json\s*/m', '', $text);
            $text = preg_replace('/^```\s*/m', '', $text);

            echo $text;
        });

        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
}