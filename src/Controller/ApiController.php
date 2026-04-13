<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Like;
use App\Repository\UserRepository;
use App\Repository\CommentRepository;
use App\Repository\LikeRepository;
use App\Repository\PublicationRepository;
use App\Service\AiChatService;
use App\Service\PlacesAutocompleteService;
use App\Service\SentimentService;
use App\Service\UnsplashService;
use App\Service\WeatherService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
#[IsGranted('ROLE_USER')]
class ApiController extends AbstractController
{

    #[Route('/like/toggle', name: 'api_like_toggle', methods: ['POST'])]
    public function toggleLike(
        Request $request,
        PublicationRepository $pubRepo,
        UserRepository $userRepo,
        LikeRepository $likeRepo,
        EntityManagerInterface $em,
    ): JsonResponse {
        $publicationId = (int) $request->request->get('publication_id', 0);
        $post = $pubRepo->find($publicationId);
        if (!$post) return $this->json(['error' => 'Post not found'], 404);

        $user = $this->getUser();
        if (!$user) return $this->json(['error' => 'Login required'], 401);

        $existingLike = $likeRepo->findUserLike($publicationId, $user->getId());

        if ($existingLike) {
            $em->remove($existingLike);
            $em->flush();
            $liked = false;
        } else {
            // User already fetched via $this->getUser()

            $like = new Like();
            $like->setPublication($post);
            $like->setUser($user);
            $em->persist($like);
            $em->flush();
            $liked = true;
        }

        return $this->json([
            'liked'     => $liked,
            'likeCount' => $likeRepo->countByPublication($publicationId),
        ]);
    }

    #[Route('/comment', name: 'api_comment_add', methods: ['POST'])]
    public function addComment(
        Request $request,
        PublicationRepository $pubRepo,
        UserRepository $userRepo,
        EntityManagerInterface $em,
        CommentRepository $commentRepo,
    ): JsonResponse {
        $publicationId = (int) $request->request->get('publication_id', 0);
        $content = trim($request->request->get('content', ''));

        if (empty($content)) return $this->json(['error' => 'Comment cannot be empty'], 400);

        $post = $pubRepo->find($publicationId);
        if (!$post) return $this->json(['error' => 'Post not found'], 404);

        $user = $this->getUser();
        if (!$user) return $this->json(['error' => 'Login required'], 401);

        $comment = new Comment();
        $comment->setPublication($post);
        $comment->setUser($user);
        $comment->setContent($content);
        $em->persist($comment);
        $em->flush();

        return $this->json([
            'id'           => $comment->getId(),
            'content'      => $comment->getContent(),
            'username'     => $user->getUsername() ?? 'User',
            'avatarPath'   => null, // Handled differently in User.php or Profile
            'timeAgo'      => 'Just now',
            'commentCount' => $commentRepo->countByPublication($publicationId),
            'isOwner'      => true,
        ]);
    }

    #[Route('/comment/{id}', name: 'api_comment_edit', methods: ['PUT'])]
    public function editComment(
        int $id,
        Request $request,
        CommentRepository $commentRepo,
        EntityManagerInterface $em,
    ): JsonResponse {
        $comment = $commentRepo->find($id);
        if (!$comment) return $this->json(['error' => 'Comment not found'], 404);

        $data = json_decode($request->getContent(), true);
        $content = trim($data['content'] ?? '');
        if (empty($content)) return $this->json(['error' => 'Content cannot be empty'], 400);

        $comment->setContent($content);
        $em->flush();

        return $this->json(['id' => $id, 'content' => $content]);
    }

    #[Route('/comment/{id}', name: 'api_comment_delete', methods: ['DELETE'])]
    public function deleteComment(
        int $id,
        CommentRepository $commentRepo,
        EntityManagerInterface $em,
    ): JsonResponse {
        $comment = $commentRepo->find($id);
        if (!$comment) return $this->json(['error' => 'Comment not found'], 404);

        $pubId = $comment->getPublication()->getId();
        $em->remove($comment);
        $em->flush();

        return $this->json([
            'deleted'      => true,
            'commentCount' => $commentRepo->countByPublication($pubId),
        ]);
    }

    #[Route('/weather/{place}', name: 'api_weather', methods: ['GET'])]
    public function weather(string $place, WeatherService $weatherService): JsonResponse
    {
        $data = $weatherService->getWeatherByLocation(urldecode($place));
        return $this->json($data ?? ['error' => 'Weather data unavailable']);
    }

    #[Route('/places/autocomplete', name: 'api_places_autocomplete', methods: ['GET'])]
    public function placesAutocomplete(
        Request $request,
        PlacesAutocompleteService $placesService,
    ): JsonResponse {
        $query = $request->query->get('q', '');
        return $this->json($placesService->getAutocompletePredictions($query));
    }

    #[Route('/chat', name: 'api_chat', methods: ['POST'])]
    public function chat(
        Request $request,
        AiChatService $chatService,
        RequestStack $requestStack,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $userMessage = trim($data['message'] ?? '');
        if (empty($userMessage)) return $this->json(['error' => 'Message cannot be empty'], 400);

        // Session-based conversation history
        $session = $requestStack->getSession();
        $history = $session->get('chat_history', []);
        $history[] = ['role' => 'user', 'content' => $userMessage];

        // Keep last 20 messages to avoid token limits
        if (count($history) > 20) {
            $history = array_slice($history, -20);
        }

        $reply = $chatService->chat($history);
        $history[] = ['role' => 'assistant', 'content' => $reply];
        $session->set('chat_history', $history);

        return $this->json(['reply' => $reply]);
    }

    #[Route('/chat/clear', name: 'api_chat_clear', methods: ['POST'])]
    public function chatClear(RequestStack $requestStack): JsonResponse
    {
        $requestStack->getSession()->remove('chat_history');
        return $this->json(['cleared' => true]);
    }

    #[Route('/unsplash/{query}', name: 'api_unsplash', methods: ['GET'])]
    public function unsplash(string $query, UnsplashService $unsplashService): JsonResponse
    {
        $photo = $unsplashService->fetchPhoto(urldecode($query));
        return $this->json($photo ?? ['error' => 'No photo found']);
    }

    #[Route('/sentiment/{id}', name: 'api_sentiment', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function sentiment(
        int $id,
        PublicationRepository $pubRepo,
        SentimentService $sentimentService,
    ): JsonResponse {
        $post = $pubRepo->find($id);
        if (!$post) return $this->json(['error' => 'Post not found'], 404);

        $result = $sentimentService->analyze($post->getContent());
        if (!$result) return $this->json(['error' => 'Analysis unavailable']);

        return $this->json($result);
    }
}
