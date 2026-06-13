<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Mpdf\Mpdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ActivitiesReservationsController extends AbstractController
{
    #[Route('/activities/{id}/reservations/pdf', name: 'app_activities_reservations_pdf', methods: ['GET'])]
    #[IsGranted('ROLE_GUIDE')]
    public function reservationsPdf(int $id, Connection $connection): Response
    {
        [$activity, $reservations] = $this->loadReservations($id, $connection);

        $safeTitle = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($activity['titre'] ?? 'activity')) ?: 'activity';
        $filename = sprintf('reservations_bundle_%s_%d.pdf', strtolower($safeTitle), (int) ($activity['idActivite'] ?? $id));

        $html = $this->renderView('activities/reservations_pdf.html.twig', [
            'activity' => $activity,
            'reservations' => $reservations,
            'generatedAt' => new \DateTimeImmutable(),
        ]);

        try {
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 12,
                'margin_right' => 12,
                'margin_top' => 14,
                'margin_bottom' => 14,
            ]);
            $mpdf->WriteHTML($html);
            $pdfContent = $mpdf->Output('', 'S');

            $response = new Response($pdfContent, Response::HTTP_OK);
            $response->headers->set('Content-Type', 'application/pdf; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . addslashes($filename) . '"');
            $response->headers->set('Cache-Control', 'private, max-age=0, must-revalidate');
            $response->headers->set('Pragma', 'public');

            return $response;
        } catch (\Exception $e) {
            throw $this->createAccessDeniedException('Unable to generate PDF: ' . $e->getMessage());
        }
    }

    #[Route('/activities/{id}/reservations/data', name: 'app_activities_reservations_data', methods: ['GET'])]
    #[IsGranted('ROLE_GUIDE')]
    public function reservationsData(int $id, Connection $connection): JsonResponse
    {
        [$activity, $reservations] = $this->loadReservations($id, $connection);

        $formatted = array_map(static function (array $reservation): array {
            $avatarUrl = '#';
            if (!empty($reservation['profileImage'])) {
                $avatarUrl = 'data:image/png;base64,' . base64_encode($reservation['profileImage']);
            }
            return [
                'idAchat' => (int) ($reservation['idAchat'] ?? 0),
                'fullName' => trim(((string) ($reservation['name'] ?? '')) . ' ' . ((string) ($reservation['last_name'] ?? ''))),
                'username' => (string) ($reservation['username'] ?? ''),
                'email' => (string) ($reservation['email'] ?? ''),
                'nbPlaces' => (int) ($reservation['nbPlaces'] ?? 0),
                'montantTotal' => (string) ($reservation['montantTotal'] ?? '0'),
                'statut' => (string) ($reservation['statut'] ?? ''),
                'dateAchat' => (string) ($reservation['dateAchat'] ?? ''),
                'avatarUrl' => $avatarUrl,
            ];
        }, $reservations);

        return $this->json([
            'success' => true,
            'activity' => [
                'idActivite' => (int) ($activity['idActivite'] ?? 0),
                'titre' => (string) ($activity['titre'] ?? 'Activity'),
            ],
            'reservations' => $formatted,
        ]);
    }

    #[Route('/activities/{id}/reservations', name: 'app_activities_reservations', methods: ['GET'])]
    #[IsGranted('ROLE_GUIDE')]
    public function reservations(int $id, Connection $connection): Response
    {
        [$activity, $reservations] = $this->loadReservations($id, $connection);

        return $this->render('activities/reservations.html.twig', [
            'activity' => $activity,
            'reservations' => $reservations,
        ]);
    }

    private function loadReservations(int $id, Connection $connection): array
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        $activity = $connection->fetchAssociative(
            'SELECT idActivite, titre, idGuide FROM activite WHERE idActivite = ?',
            [$id]
        );

        if (!is_array($activity)) {
            throw $this->createNotFoundException('Activity not found.');
        }

        if ((int) ($activity['idGuide'] ?? 0) !== (int) $user->getId()) {
            throw $this->createAccessDeniedException('You can only view reservations for your own activities.');
        }

        $reservations = $connection->fetchAllAssociative(
            'SELECT a.idAchat, DATE_FORMAT(a.dateAchat, "%Y-%m-%d %H:%i") AS dateAchat, a.montantTotal, a.statut, a.nbPlaces,
                    u.id AS userId, u.name, u.last_name, u.username, u.email, p.image AS profileImage
             FROM achat a
             INNER JOIN user u ON u.id = a.idClient
             LEFT JOIN profile p ON p.id_user = u.id
             WHERE a.idActivite = ?
             ORDER BY a.dateAchat DESC, a.idAchat DESC',
            [$id]
        );

        return [$activity, $reservations];
    }
}
