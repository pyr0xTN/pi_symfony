<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class GuideActivitiesStatsController extends AbstractController
{
    #[Route('/activities/stats', name: 'app_activities_stats', methods: ['GET'])]
    #[IsGranted('ROLE_GUIDE')]
    public function index(Connection $connection): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        $statsData = $this->buildStatsData($connection, $user);

        return $this->render('activities/stats.html.twig', $statsData);
    }

    #[Route('/activities/stats/data', name: 'app_activities_stats_data', methods: ['GET'])]
    #[IsGranted('ROLE_GUIDE')]
    public function data(Connection $connection): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            return $this->json([
                'success' => false,
                'message' => 'You must be logged in.',
            ], 403);
        }

        return $this->json([
            'success' => true,
            'stats' => $this->buildStatsData($connection, $user),
        ]);
    }

    private function buildStatsData(Connection $connection, User $user): array
    {
        $activities = $connection->fetchAllAssociative(
            'SELECT a.idActivite,
                    a.titre,
                    a.statut,
                    a.dateCreation,
                    a.dateActivite,
                    a.prix,
                    COALESCE(COUNT(ach.idAchat), 0) AS reservations_count,
                    COALESCE(SUM(ach.nbPlaces), 0) AS booked_places,
                    COALESCE(SUM(ach.montantTotal), 0) AS revenue_total
             FROM activite a
             LEFT JOIN achat ach ON ach.idActivite = a.idActivite
             WHERE a.idGuide = ?
             GROUP BY a.idActivite, a.titre, a.statut, a.dateCreation, a.dateActivite, a.prix
             ORDER BY a.dateCreation DESC, a.idActivite DESC',
            [(int) $user->getId()]
        );

        $seasonStats = [
            'winter' => ['label' => 'Winter', 'range' => 'Dec - Feb', 'activityCount' => 0, 'reservationCount' => 0, 'bookedPlaces' => 0, 'revenue' => 0.0, 'averageReservations' => 0.0],
            'spring' => ['label' => 'Spring', 'range' => 'Mar - May', 'activityCount' => 0, 'reservationCount' => 0, 'bookedPlaces' => 0, 'revenue' => 0.0, 'averageReservations' => 0.0],
            'summer' => ['label' => 'Summer', 'range' => 'Jun - Aug', 'activityCount' => 0, 'reservationCount' => 0, 'bookedPlaces' => 0, 'revenue' => 0.0, 'averageReservations' => 0.0],
            'autumn' => ['label' => 'Autumn', 'range' => 'Sep - Nov', 'activityCount' => 0, 'reservationCount' => 0, 'bookedPlaces' => 0, 'revenue' => 0.0, 'averageReservations' => 0.0],
        ];

        $monthlyCreation = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthlyCreation[$month] = 0;
        }

        $totalActivities = count($activities);
        $activeActivities = 0;
        $inactiveActivities = 0;
        $totalReservations = 0;
        $totalBookedPlaces = 0;
        $totalRevenue = 0.0;
        $topActivity = null;
        $activitySeries = [];

        foreach ($activities as $activity) {
            $status = strtolower(trim((string) ($activity['statut'] ?? '')));
            if ($status === 'actif') {
                $activeActivities++;
            } else {
                $inactiveActivities++;
            }

            $reservationsCount = (int) ($activity['reservations_count'] ?? 0);
            $bookedPlaces = (int) ($activity['booked_places'] ?? 0);
            $revenue = (float) ($activity['revenue_total'] ?? 0);

            $totalReservations += $reservationsCount;
            $totalBookedPlaces += $bookedPlaces;
            $totalRevenue += $revenue;

            $activitySeries[] = [
                'label' => (string) ($activity['titre'] ?? 'Activity'),
                'revenue' => $revenue,
                'reservations' => $reservationsCount,
                'bookedPlaces' => $bookedPlaces,
            ];

            if ($topActivity === null || $reservationsCount > $topActivity['reservationsCount'] || ($reservationsCount === $topActivity['reservationsCount'] && $revenue > $topActivity['revenue'])) {
                $topActivity = [
                    'idActivite' => (int) ($activity['idActivite'] ?? 0),
                    'titre' => (string) ($activity['titre'] ?? 'Activity'),
                    'reservationsCount' => $reservationsCount,
                    'bookedPlaces' => $bookedPlaces,
                    'revenue' => $revenue,
                    'statut' => (string) ($activity['statut'] ?? ''),
                    'dateCreation' => (string) ($activity['dateCreation'] ?? ''),
                ];
            }

            $creationRaw = trim((string) ($activity['dateCreation'] ?? ''));
            if ($creationRaw !== '') {
                try {
                    $creationDate = new \DateTimeImmutable($creationRaw);
                    $month = (int) $creationDate->format('n');
                    $seasonKey = $this->seasonKeyForMonth($month);

                    $seasonStats[$seasonKey]['activityCount']++;
                    $seasonStats[$seasonKey]['reservationCount'] += $reservationsCount;
                    $seasonStats[$seasonKey]['bookedPlaces'] += $bookedPlaces;
                    $seasonStats[$seasonKey]['revenue'] += $revenue;
                    $monthlyCreation[$month]++;
                } catch (\Throwable) {
                    // Ignore invalid dates and keep the rest of the stats available.
                }
            }
        }

        usort($activitySeries, static function (array $a, array $b): int {
            return ($b['revenue'] <=> $a['revenue']) ?: ($b['reservations'] <=> $a['reservations']);
        });
        $activitySeries = array_slice($activitySeries, 0, 6);

        $bestSeason = null;
        $maxSeasonAverage = 0.0;
        foreach ($seasonStats as $key => $data) {
            $averageReservations = $data['activityCount'] > 0 ? $data['reservationCount'] / $data['activityCount'] : 0.0;

            $seasonStats[$key]['averageReservations'] = $averageReservations;
            $maxSeasonAverage = max($maxSeasonAverage, $averageReservations);

            if ($bestSeason === null || $averageReservations > $bestSeason['averageReservations'] || ($averageReservations === $bestSeason['averageReservations'] && $data['reservationCount'] > $bestSeason['reservationCount'])) {
                $bestSeason = [
                    'key' => $key,
                    'label' => $data['label'],
                    'range' => $data['range'],
                    'averageReservations' => $averageReservations,
                    'reservationCount' => $data['reservationCount'],
                    'activityCount' => $data['activityCount'],
                ];
            }
        }

        $averageReservationsPerActivity = $totalActivities > 0 ? $totalReservations / $totalActivities : 0.0;

        return [
            'totalActivities' => $totalActivities,
            'activeActivities' => $activeActivities,
            'inactiveActivities' => $inactiveActivities,
            'totalReservations' => $totalReservations,
            'totalBookedPlaces' => $totalBookedPlaces,
            'totalRevenue' => $totalRevenue,
            'averageReservationsPerActivity' => $averageReservationsPerActivity,
            'bestSeason' => $bestSeason,
            'seasonStats' => $seasonStats,
            'maxSeasonAverage' => $maxSeasonAverage,
            'monthlyCreation' => $monthlyCreation,
            'topActivity' => $topActivity,
            'activitySeries' => $activitySeries,
        ];
    }

    private function seasonKeyForMonth(int $month): string
    {
        return match ($month) {
            12, 1, 2 => 'winter',
            3, 4, 5 => 'spring',
            6, 7, 8 => 'summer',
            default => 'autumn',
        };
    }
}