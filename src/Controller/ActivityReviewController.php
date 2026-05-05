<?php

namespace App\Controller;

use App\Entity\ActivityReview;
use App\Entity\User;
use App\Repository\ActivityReviewRepository;
use App\Repository\UserReservationRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
#[IsGranted('ROLE_USER')]
class ActivityReviewController extends AbstractController
{
    #[Route('/activities/{activityId}/reviews', name: 'api_activity_reviews_list', methods: ['GET'])]
    public function list(
        int $activityId,
        Connection $connection,
        ActivityReviewRepository $reviewRepository,
        UserReservationRepository $reservationRepository,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            return $this->json(['success' => false, 'message' => 'Login required.'], 403);
        }

        $activity = $this->loadActivity($connection, $activityId);
        if ($activity === null) {
            return $this->json(['success' => false, 'message' => 'Activity not found.'], 404);
        }

        $activityPassed = $this->isActivityPassed($activity);
        $hasReserved = $reservationRepository->hasUserReservedActivity((int) $user->getId(), $activityId);
        $canSubmit = $activityPassed && $hasReserved;
        
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $allReviews = $reviewRepository->findByActivityId($activityId);
        $myReview = $reviewRepository->findUserReview($activityId, (int) $user->getId());

        return $this->json([
            'success' => true,
            'activity' => [
                'id' => (int) ($activity['idActivite'] ?? $activityId),
                'title' => (string) ($activity['titre'] ?? 'Activity'),
                'guideName' => (string) ($activity['guide_display_name'] ?? 'Guide'),
            ],
            'canSubmit' => $canSubmit,
            'activityPassed' => $activityPassed,
            'hasReserved' => $hasReserved,
            'myReview' => $myReview?->getContent() ?? '',
            'reviews' => array_map(fn (ActivityReview $review) => $this->serializeReview($review, $isAdmin), $allReviews),
        ]);
    }

    #[Route('/activities/{activityId}/reviews', name: 'api_activity_reviews_save', methods: ['POST'])]
    public function save(
        int $activityId,
        Request $request,
        Connection $connection,
        ActivityReviewRepository $reviewRepository,
        UserReservationRepository $reservationRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            return $this->json(['success' => false, 'message' => 'Login required.'], 403);
        }

        $activity = $this->loadActivity($connection, $activityId);
        if ($activity === null) {
            return $this->json(['success' => false, 'message' => 'Activity not found.'], 404);
        }

        if (!$this->isCsrfTokenValid('activity-review-' . $activityId, (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Invalid request token.'], 400);
        }

        $content = trim((string) $request->request->get('content', ''));
        if ($content === '') {
            return $this->json(['success' => false, 'message' => 'Review cannot be empty.'], 400);
        }

        // Check if activity has passed
        if (!$this->isActivityPassed($activity)) {
            return $this->json(['success' => false, 'message' => 'You can only review after the activity is completed.'], 403);
        }

        // Check if user has reserved this activity
        if (!$reservationRepository->hasUserReservedActivity((int) $user->getId(), $activityId)) {
            return $this->json(['success' => false, 'message' => 'You must reserve this activity to review it.'], 403);
        }

        $existingReview = $reviewRepository->findUserReview($activityId, (int) $user->getId());
        if ($existingReview instanceof ActivityReview) {
            $existingReview->setContent($content);
            $existingReview->setUpdatedAt(new \DateTimeImmutable());
            $review = $existingReview;
        } else {
            $review = new ActivityReview();
            $review->setActivityId($activityId);
            $review->setUser($user);
            $review->setContent($content);
            $review->setCreatedAt(new \DateTimeImmutable());
            $entityManager->persist($review);
        }

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'review' => $this->serializeReview($review, $this->isGranted('ROLE_ADMIN')),
            'message' => $existingReview instanceof ActivityReview ? 'Review updated.' : 'Review saved.',
        ]);
    }

    #[Route('/activity-reviews/{reviewId}', name: 'api_activity_reviews_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $reviewId, ActivityReviewRepository $reviewRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $review = $reviewRepository->find($reviewId);
        if (!$review instanceof ActivityReview) {
            return $this->json(['success' => false, 'message' => 'Review not found.'], 404);
        }

        $entityManager->remove($review);
        $entityManager->flush();

        return $this->json(['success' => true, 'deleted' => true]);
    }

    private function loadActivity(Connection $connection, int $activityId): ?array
    {
        $activity = $connection->fetchAssociative(
            "SELECT a.idActivite, a.titre, a.idGuide, a.dateActivite, a.statut,
                    COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.name, ''), ' ', COALESCE(u.last_name, ''))), ''), u.username, 'Guide') AS guide_display_name
             FROM activite a
             LEFT JOIN `user` u ON u.id = a.idGuide
             WHERE a.idActivite = ?",
            [$activityId]
        );

        return is_array($activity) ? $activity : null;
    }

    private function buildEligibility(Connection $connection, array $activity, int $userId): array
    {
        $purchase = $connection->fetchAssociative(
            'SELECT 1 AS has_purchase FROM achat WHERE idActivite = ? AND idClient = ? LIMIT 1',
            [(int) ($activity['idActivite'] ?? 0), $userId]
        );

        $guideId = (int) ($activity['idGuide'] ?? 0);
        $activityPassed = $this->isActivityPassed($activity);

        return [
            'canSubmit' => is_array($purchase) && $activityPassed && $guideId !== $userId,
        ];
    }

    private function isActivityPassed(array $activity): bool
    {
        $status = strtolower(trim((string) ($activity['statut'] ?? '')));
        if (in_array($status, ['passed', 'pass', 'past', 'completed', 'complete', 'done', 'finished', 'termine', 'terminé', 'terminee', 'terminée'], true)) {
            return true;
        }

        $activityDateRaw = trim((string) ($activity['dateActivite'] ?? ''));
        if ($activityDateRaw === '') {
            return false;
        }

        try {
            return new \DateTimeImmutable($activityDateRaw) <= new \DateTimeImmutable('now');
        } catch (\Throwable) {
            return false;
        }
    }

    private function serializeReview(ActivityReview $review, bool $isAdmin): array
    {
        $user = $review->getUser();
        $displayName = 'Anonymous traveler';
        if ($isAdmin && $user instanceof User) {
            $fullName = trim($user->getFullName());
            $displayName = $fullName !== '' ? $fullName : (string) ($user->getUsername() ?? 'User');
        }

        return [
            'id' => (int) ($review->getId() ?? 0),
            'content' => (string) ($review->getContent() ?? ''),
            'timeAgo' => $review->getTimeAgo(),
            'createdAt' => $review->getCreatedAt() ? $review->getCreatedAt()->format('Y-m-d H:i') : '',
            'anonymousLabel' => 'Anonymous traveler',
            'displayName' => $displayName,
            'canDelete' => $isAdmin,
        ];
    }
}