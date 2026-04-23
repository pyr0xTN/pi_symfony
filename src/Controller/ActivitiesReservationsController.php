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

        $pdfContent = $this->buildReservationsPdf($activity, $reservations);
        $safeTitle = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($activity['titre'] ?? 'activity')) ?: 'activity';
        $filename = sprintf('reservations_%s_%d.pdf', strtolower($safeTitle), (int) ($activity['idActivite'] ?? $id));

        $response = new Response($pdfContent);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Content-Length', (string) strlen($pdfContent));

        return $response;
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

    private function truncateText(string $value, int $maxLength): string
    {
        $value = trim($value);
        if (strlen($value) <= $maxLength) {
            return $value;
        }

        return substr($value, 0, max(0, $maxLength - 3)) . '...';
    }

    private function buildReservationsPdf(array $activity, array $reservations): string
    {
        $columns = [
            ['label' => 'ID', 'key' => 'idAchat', 'width' => 40],
            ['label' => 'Client', 'key' => 'client', 'width' => 120],
            ['label' => 'Email', 'key' => 'email', 'width' => 145],
            ['label' => 'Places', 'key' => 'nbPlaces', 'width' => 45],
            ['label' => 'Amount', 'key' => 'montantTotal', 'width' => 55],
            ['label' => 'Status', 'key' => 'statut', 'width' => 55],
            ['label' => 'Date', 'key' => 'dateAchat', 'width' => 80],
        ];

        $x = 28;
        $tableWidth = array_sum(array_column($columns, 'width'));
        $titleY = 804;
        $metaY = 784;
        $tableTopY = 742;
        $headerHeight = 20;
        $rowHeight = 18;
        $tableBottomY = 62;
        $maxRows = (int) floor(($tableTopY - $headerHeight - $tableBottomY) / $rowHeight);

        $content = '';

        $content .= $this->pdfText('F2', 18, $x, $titleY, 'Activity Reservations Report');
        $content .= $this->pdfText('F1', 11, $x, $metaY, 'Activity: ' . (string) ($activity['titre'] ?? 'Activity'));
        $content .= $this->pdfText('F1', 10, $x, $metaY - 16, 'Generated at: ' . (new \DateTimeImmutable())->format('Y-m-d H:i'));
        $content .= $this->pdfText('F1', 10, $x + 380, $metaY - 16, 'Total reservations: ' . (string) count($reservations));

        $content .= "q\n0.91 0.95 0.99 rg\n" . sprintf('%d %d %d %d re f', $x, $tableTopY - $headerHeight, $tableWidth, $headerHeight) . "\nQ\n";

        $cursorX = $x;
        foreach ($columns as $column) {
            $content .= $this->pdfText('F2', 10, $cursorX + 4, $tableTopY - 14, (string) $column['label']);
            $cursorX += (int) $column['width'];
        }

        $content .= "q\n0.72 0.78 0.84 RG\n0.6 w\n";
        $content .= sprintf('%d %d m %d %d l S' . "\n", $x, $tableTopY, $x + $tableWidth, $tableTopY);
        $content .= sprintf('%d %d m %d %d l S' . "\n", $x, $tableTopY - $headerHeight, $x + $tableWidth, $tableTopY - $headerHeight);
        $content .= sprintf('%d %d m %d %d l S' . "\n", $x, $tableBottomY, $x + $tableWidth, $tableBottomY);

        $lineX = $x;
        foreach ($columns as $column) {
            $content .= sprintf('%d %d m %d %d l S' . "\n", $lineX, $tableTopY, $lineX, $tableBottomY);
            $lineX += (int) $column['width'];
        }
        $content .= sprintf('%d %d m %d %d l S' . "\n", $x + $tableWidth, $tableTopY, $x + $tableWidth, $tableBottomY);
        $content .= "Q\n";

        $rowsToPrint = array_slice($reservations, 0, max(0, $maxRows));

        if ($rowsToPrint === []) {
            $content .= $this->pdfText('F1', 11, $x + 8, $tableTopY - $headerHeight - 24, 'No reservations found for this activity.');
        } else {
            foreach ($rowsToPrint as $rowIndex => $reservation) {
                $rowTop = $tableTopY - $headerHeight - ($rowIndex * $rowHeight);
                $rowBottom = $rowTop - $rowHeight;

                if ($rowBottom < $tableBottomY) {
                    break;
                }

                if ($rowIndex % 2 === 1) {
                    $content .= "q\n0.97 0.98 0.99 rg\n" . sprintf('%d %d %d %d re f', $x, $rowBottom, $tableWidth, $rowHeight) . "\nQ\n";
                }

                $clientName = trim(((string) ($reservation['name'] ?? '')) . ' ' . ((string) ($reservation['last_name'] ?? '')));
                $username = trim((string) ($reservation['username'] ?? ''));
                if ($username !== '') {
                    $clientName .= ' (' . $username . ')';
                }

                $cells = [
                    $this->truncateText((string) ($reservation['idAchat'] ?? ''), 8),
                    $this->truncateText($clientName, 26),
                    $this->truncateText((string) ($reservation['email'] ?? ''), 30),
                    $this->truncateText((string) ($reservation['nbPlaces'] ?? '0'), 6),
                    $this->truncateText((string) ($reservation['montantTotal'] ?? '0') . ' DT', 12),
                    $this->truncateText((string) ($reservation['statut'] ?? ''), 12),
                    $this->truncateText((string) ($reservation['dateAchat'] ?? ''), 16),
                ];

                $cellX = $x;
                foreach ($columns as $index => $column) {
                    $content .= $this->pdfText('F1', 9, $cellX + 4, $rowTop - 12, $cells[$index] ?? '');
                    $cellX += (int) $column['width'];
                }

                $content .= "q\n0.9 0.93 0.96 RG\n0.4 w\n";
                $content .= sprintf('%d %d m %d %d l S' . "\n", $x, $rowBottom, $x + $tableWidth, $rowBottom);
                $content .= "Q\n";
            }
        }

        $overflow = count($reservations) - count($rowsToPrint);
        if ($overflow > 0) {
            $content .= $this->pdfText('F1', 9, $x, 48, '+' . (string) $overflow . ' more reservations are not shown on this page.');
        }

        $contentStream = "q\n" . $content . "Q";

        $objects = [];
        $objects[] = '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj';
        $objects[] = '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj';
        $objects[] = '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 6 0 R >> >> /Contents 5 0 R >> endobj';
        $objects[] = '4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj';
        $objects[] = '5 0 obj << /Length ' . strlen($contentStream) . " >> stream\n" . $contentStream . "\nendstream endobj";
        $objects[] = '6 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >> endobj';

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object . "\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n";
        $pdf .= '0 ' . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= 'trailer << /Size ' . (count($objects) + 1) . ' /Root 1 0 R >>' . "\n";
        $pdf .= "startxref\n";
        $pdf .= $xrefOffset . "\n";
        $pdf .= "%%EOF";

        return $pdf;
    }

    private function pdfText(string $font, int $size, int $x, int $y, string $text): string
    {
        return "BT\n/{$font} {$size} Tf\n" . sprintf('1 0 0 1 %d %d Tm', $x, $y) . "\n(" . $this->escapePdfText($text) . ") Tj\nET\n";
    }

    private function escapePdfText(string $text): string
    {
        $text = trim($text);
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                $text = $converted;
            }
        }

        $text = preg_replace('/[^\x20-\x7E]/', ' ', $text) ?? '';

        return str_replace(
            ['\\', '(', ')', "\r", "\n", "\t"],
            ['\\\\', '\\(', '\\)', ' ', ' ', ' '],
            $text
        );
    }
}
