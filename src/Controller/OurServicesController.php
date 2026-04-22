<?php

namespace App\Controller;

use App\Repository\ServicesRepository;
use App\Service\AiSearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Knp\Component\Pager\PaginatorInterface;

/**
 * Public-facing services listing (hotels + vols combined).
 * Mirrors: ServicesController (JavaFX) — the card grid view.
 */
#[Route('/ourservices')]
class OurServicesController extends AbstractController
{
    public function __construct(
        private ServicesRepository $servicesRepo,
        private PaginatorInterface $paginator,
        private AiSearchService $aiSearchService
    ) {}

    #[Route('', name: 'ourservices_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $aiQuery = $request->query->get('ai_q', '');
        $search  = $request->query->get('q', '');
        
        $aiFilters = null;
        $qb = null;

        if (!empty($aiQuery)) {
            // User requested an AI Smart Search
            $aiFilters = $this->aiSearchService->extractFilters($aiQuery);
            
            if ($aiFilters) {
                // Determine query based on AI extraction
                $qb = $this->servicesRepo->findWithAiQueryBuilder($aiFilters);
            } else {
                // Fallback to normal search if AI failed or API key missing
                $qb = $this->servicesRepo->findAllQueryBuilder($aiQuery);
            }
        } else {
            // Normal fallback
            $qb = $this->servicesRepo->findAllQueryBuilder($search);
        }

        $pagination = $this->paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            8 // items per page
        );

        return $this->render('ourservices/index.html.twig', [
            'active_page' => 'ourservices',
            'pagination'  => $pagination,
            'aiQuery'     => $aiQuery,
            'aiFilters'   => $aiFilters,
        ]);
    }

    #[Route('/hotel/{id}', name: 'hotel_showdetails', methods: ['GET'])]
    public function hotelDetail(int $id): Response
    {
        $hotel = $this->servicesRepo->findOneBy(['idService' => $id, 'type' => 'hotel']);
        if (!$hotel) {
            throw $this->createNotFoundException('Hôtel introuvable.');
        }

        return $this->render('ourservices/hotel_details.html.twig', [
            'active_page' => 'ourservices',
            'hotel'       => $hotel,
        ]);
    }

    #[Route('/vol/{id}', name: 'vol_showdetails', methods: ['GET'])]
    public function volDetail(int $id): Response
    {
        $vol = $this->servicesRepo->findOneBy(['idService' => $id, 'type' => 'vol']);
        if (!$vol) {
            throw $this->createNotFoundException('Vol introuvable.');
        }

        return $this->render('ourservices/vol_details.html.twig', [
            'active_page' => 'ourservices',
            'vol'         => $vol,
        ]);
    }
}
