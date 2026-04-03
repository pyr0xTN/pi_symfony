<?php

namespace App\Controller;

use App\Repository\PublicationRepository;
use App\Service\GeocodingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MapController extends AbstractController
{
    #[Route('/map', name: 'app_map')]
    public function index(): Response
    {
        return $this->render('front/map.html.twig');
    }

    #[Route('/api/map/markers', name: 'api_map_markers', methods: ['GET'])]
    public function markers(
        PublicationRepository $pubRepo,
        GeocodingService $geocoding,
    ): JsonResponse {
        $posts = $pubRepo->findWithPlaces();
        $markers = [];

        foreach ($posts as $post) {
            $coords = $geocoding->geocode($post->getPlace());
            if ($coords) {
                $markers[] = [
                    'id'      => $post->getId(),
                    'lat'     => $coords['lat'],
                    'lon'     => $coords['lon'],
                    'place'   => $post->getPlace(),
                    'content' => mb_substr($post->getContent(), 0, 100) . (mb_strlen($post->getContent()) > 100 ? '…' : ''),
                    'author'  => $post->getClient()?->getUsername() ?? 'Anonymous',
                    'image'   => $post->getImagePath(),
                    'date'    => $post->getTimeAgo(),
                ];
            }
        }

        return $this->json($markers);
    }
}
