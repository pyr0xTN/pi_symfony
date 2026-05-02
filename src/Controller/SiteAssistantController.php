<?php

namespace App\Controller;

use App\Service\SiteAssistantService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SiteAssistantController extends AbstractController
{
    #[Route('/assistant/chat', name: 'app_site_assistant_chat', methods: ['POST'])]
    public function chat(Request $request, SiteAssistantService $siteAssistantService): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true);
            if (!is_array($payload)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid payload.',
                ], Response::HTTP_BAD_REQUEST);
            }

            $message = trim((string) ($payload['message'] ?? ''));
            if ($message === '') {
                return $this->json([
                    'success' => false,
                    'error' => 'Message is required.',
                ], Response::HTTP_BAD_REQUEST);
            }

            $result = $siteAssistantService->handle($message, $this->isGranted('ROLE_ADMIN'));

            return $this->json([
                'success' => true,
                'kind' => $result['kind'],
                'reply' => $result['reply'],
                'action' => $result['action'] ?? null,
            ]);
        } catch (\Throwable $exception) {
            return $this->json([
                'success' => false,
                'error' => 'Assistant is temporarily unavailable.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
