<?php

namespace App\Controller;

use App\Repository\ServicesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public-facing services listing (hotels + vols combined).
 * Mirrors: ServicesController (JavaFX) — the card grid view.
 */
#[Route('/ourservices')]
class OurServicesController extends AbstractController
{
    public function __construct(
        private ServicesRepository $servicesRepo,
    ) {}

    // ──────────────────────────────────────────────
    // LIST ALL SERVICES (hotels + vols)
    // ──────────────────────────────────────────────

    #[Route('', name: 'ourservices_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search   = $request->query->get('q', '');
        $services = $search
            ? $this->servicesRepo->findBySearch($search)
            : $this->servicesRepo->findAll();

        return $this->render('ourservices/index.html.twig', [
            'active_page' => 'ourservices',
            'services'    => $services,
        ]);
    }


}
