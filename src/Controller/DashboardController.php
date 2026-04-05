<?php

namespace App\Controller;

use App\Repository\ServicesRepository;
use App\Repository\ReservationsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard')]
class DashboardController extends AbstractController
{
    public function __construct(
        private ServicesRepository      $servicesRepo,
        private ReservationsRepository  $reservationsRepo,
    ) {}

    /**
     * Main dashboard overview — shows stats + service list + reservation list.
     * Mirrors: DashboardServicesController (JavaFX)
     */
    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filter = $request->query->get('filter', '');
        $search = $request->query->get('q', '');

        // Fetch services, optionally filtered by type (hotel / vol)
        if ($filter) {
            $services = $this->servicesRepo->findBy(['type' => $filter]);
        } elseif ($search) {
            $services = $this->servicesRepo->findBySearch($search);
        } else {
            $services = $this->servicesRepo->findAll();
        }

        return $this->render('dashboard/index.html.twig', [
            'active_page'       => 'dashboard',
            'hotel_count'       => $this->servicesRepo->count(['type' => 'hotel']),
            'vol_count'         => $this->servicesRepo->count(['type' => 'vol']),
            'reservation_count' => $this->reservationsRepo->count([]),
            'services'          => $services,
            'reservations'      => $this->reservationsRepo->findLatest(10),
            'filter'            => $filter,
        ]);
    }
}
