<?php

namespace App\Controller;

use App\Entity\Reservations;
use App\Entity\Services;
use App\Form\ReservationType;
use App\Repository\ReservationsRepository;
use App\Repository\ServicesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles reservation creation, listing and deletion.
 * Mirrors: AddReservationController + ReservationsController (JavaFX)
 */
#[Route('/reservation')]
class ReservationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ReservationsRepository $reservationsRepo,
        private ServicesRepository     $servicesRepo,
    ) {}

    // ──────────────────────────────────────────────
    // LIST ALL RESERVATIONS
    // Mirrors: ReservationsController (JavaFX) — card grid
    // ──────────────────────────────────────────────

    #[Route('', name: 'reservations_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search       = $request->query->get('q', '');
        $reservations = $search
            ? $this->reservationsRepo->findBySearch($search)
            : $this->reservationsRepo->findAll();

        return $this->render('reservation/index.html.twig', [
            'active_page'  => 'reservations',
            'reservations' => $reservations,
        ]);
    }

    // ──────────────────────────────────────────────
    // CREATE  (called from hotel/show or vol/show — "Réserver" button)
    // Mirrors: AddReservationController (JavaFX)
    // ──────────────────────────────────────────────

    #[Route('/new', name: 'reservation_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        // The service (hotel or vol) is passed as a query param from the details page
        $serviceId   = $request->query->getInt('serviceId');
        $serviceType = $request->query->get('serviceType', '');

        $service = $this->servicesRepo->findOneBy(['idService' => $serviceId]);
        if (!$service) {
            throw $this->createNotFoundException('Service introuvable.');
        }

        $reservation = new Reservations();
        $reservation->setStatut('En attente');
        $reservation->setIdService($service);

        $form = $this->createForm(ReservationType::class, $reservation, [
            'service_type' => $serviceType,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // For vols: the seat number comes from the seat map selection
            if ($serviceType === 'vol') {
                $seatNb = (int) $form->get('siege')->getData();
                $reservation->setSeatNb($seatNb);
            } else {
                $reservation->setSeatNb(0); // Hotels don't use seat numbers
            }

            $this->em->persist($reservation);
            $this->em->flush();

            $this->addFlash('success', 'Réservation créée avec succès !');
            return $this->redirectToRoute('reservations_index');
        }

        // Build the seat map for vols
        $seats = [];
        if ($serviceType === 'vol') {
            $seats = $this->buildSeatMap($service);
        }

        return $this->render('reservation/new.html.twig', [
            'active_page' => 'reservations',
            'form'        => $form,
            'service'     => $service,
            'serviceType' => $serviceType,
            'seats'       => $seats,
        ]);
    }

    // ──────────────────────────────────────────────
    // SHOW DETAIL
    // ──────────────────────────────────────────────

    #[Route('/{id}', name: 'reservation_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $reservation = $this->reservationsRepo->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException("Réservation #$id introuvable.");
        }

        return $this->render('reservation/show.html.twig', [
            'active_page' => 'reservations',
            'reservation' => $reservation,
        ]);
    }

    // ──────────────────────────────────────────────
    // DELETE
    // ──────────────────────────────────────────────

    #[Route('/{id}/delete', name: 'reservation_delete', methods: ['GET', 'POST'])]
    public function delete(Request $request, int $id): Response
    {
        $reservation = $this->reservationsRepo->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException("Réservation #$id introuvable.");
        }

        // Accept both GET (with confirm dialog in Twig) and POST (with CSRF)
        if ($request->isMethod('POST')) {
            if ($this->isCsrfTokenValid('delete_resa_' . $id, $request->request->get('_token'))) {
                $this->em->remove($reservation);
                $this->em->flush();
                $this->addFlash('success', 'Réservation supprimée.');
            }
        } else {
            // GET — simple confirmation via JS confirm() in the Twig link
            $this->em->remove($reservation);
            $this->em->flush();
            $this->addFlash('success', 'Réservation supprimée.');
        }

        return $this->redirectToRoute('reservations_index');
    }

    // ──────────────────────────────────────────────
    // PRIVATE HELPERS
    // ──────────────────────────────────────────────

    /**
     * Builds the seat map array for a Vol.
     * Mirrors the dynamic seat grid in ReservationForm.fxml (JavaFX).
     *
     * Returns an array of ['label' => 'A1', 'occupied' => false].
     */
    private function buildSeatMap(Services $vol): array
    {
        $capacity = $vol->getCapacite();

        // Fetch already-reserved seat numbers for this vol
        $takenSeats = array_map(
            fn(Reservations $r) => $r->getSeatNb(),
            $this->reservationsRepo->findBy(['idService' => $vol])
        );

        $rows    = range('A', 'Z');
        $cols    = 6; // seats per row (3+3 aircraft style)
        $seats   = [];
        $counter = 1;

        foreach ($rows as $row) {
            for ($col = 1; $col <= $cols; $col++) {
                if ($counter > $capacity) break 2;

                $seats[] = [
                    'label'    => $row . $col,
                    'number'   => $counter,
                    'occupied' => in_array($counter, $takenSeats, true),
                ];
                $counter++;
            }
        }

        return $seats;
    }
}
