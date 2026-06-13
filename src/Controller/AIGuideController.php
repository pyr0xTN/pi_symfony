<?php
// src/Controller/AIGuideController.php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AIGuideController extends AbstractController
{
    #[Route('/ai-guide', name: 'app_ai_guide')]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        return $this->render('ai_guide/index.html.twig');
    }

    #[Route('/ai-guide/analyze', name: 'app_ai_guide_analyze', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function analyzeImage(Request $request, HttpClientInterface $httpClient): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['success' => false, 'error' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        }

        $imageDataUrl = (string) ($payload['imageData'] ?? '');
        if ($imageDataUrl === '' || !str_starts_with($imageDataUrl, 'data:image/')) {
            return $this->json(['success' => false, 'error' => 'Missing image'], Response::HTTP_BAD_REQUEST);
        }

        // Extract base64 from data URL
        $separatorPos = strpos($imageDataUrl, ',');
        if ($separatorPos === false) {
            return $this->json(['success' => false, 'error' => 'Invalid image format'], Response::HTTP_BAD_REQUEST);
        }

        $base64Data = substr($imageDataUrl, $separatorPos + 1);
        $imageData = base64_decode($base64Data, true);
        if ($imageData === false || $imageData === '') {
            return $this->json(['success' => false, 'error' => 'Image decode failed'], Response::HTTP_BAD_REQUEST);
        }

        // Get Face API URL
        $faceApiUrl = trim((string) ($_ENV['FACE_ID_API_URL'] ?? $_SERVER['FACE_ID_API_URL'] ?? getenv('FACE_ID_API_URL') ?: ''));
        if ($faceApiUrl === '') {
            $faceApiUrl = 'http://127.0.0.1:8001';
        }

        try {
            // Call Python service to analyze the image and suggest locations
            $analyzeResponse = $httpClient->request('POST', rtrim($faceApiUrl, '/') . '/analyze-location', [
                'json' => ['image_data' => $imageDataUrl],
                'headers' => ['Accept' => 'application/json'],
                'timeout' => 15.0,
            ]);

            $status = $analyzeResponse->getStatusCode();
            if ($status >= 400) {
                return $this->json([
                    'success' => false,
                    'error' => 'Location analysis service failed.',
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }

            $responseData = $analyzeResponse->toArray(false);
            if (!isset($responseData['suggestions'])) {
                return $this->json([
                    'success' => false,
                    'error' => 'No suggestions found for this image.',
                ], Response::HTTP_BAD_REQUEST);
            }

            return $this->json([
                'success' => true,
                'suggestions' => $responseData['suggestions'],
                'confidence' => $responseData['confidence'] ?? 0.0,
                'description' => $responseData['description'] ?? '',
                'photo_summary' => $responseData['photo_summary'] ?? '',
                'best_time_to_visit' => $responseData['best_time_to_visit'] ?? '',
                'visit_window' => $responseData['visit_window'] ?? '',
                'analysis_source' => $responseData['analysis_source'] ?? 'opencv',
            ]);

        } catch (\Throwable $exception) {
            return $this->json([
                'success' => false,
                'error' => 'Location analysis service is unavailable. Please try again later.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}
