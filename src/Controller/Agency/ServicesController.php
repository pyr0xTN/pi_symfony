<?php

namespace App\Controller\Agency;

use App\Repository\ServicesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/agency/services')]
class ServicesController extends AbstractController
{
    public function __construct(
        private ServicesRepository $servicesRepo,
        private ChartBuilderInterface $chartBuilder,
        private PaginatorInterface $paginator,
    ) {}

    #[Route('', name: 'agency_services_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search   = $request->query->get('q', '');
        $qb       = $this->servicesRepo->findAllQueryBuilder($search);

        $pagination = $this->paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            8 // items per page
        );

        // For charts, we need all services (not just the paged ones)
        $allServices = $search
            ? $this->servicesRepo->findBySearch($search)
            : $this->servicesRepo->findAll();

        // ── Stats Calculation ──
        $hotels = 0;
        $flights = 0;
        $available = 0;
        foreach ($allServices as $s) {
            if ($s->getType() === 'hotel') $hotels++;
            else $flights++;
            
            if ($s->getDisponibilite()) $available++;
        }

        // ── Distribution Chart ──
        $chart = $this->chartBuilder->createChart(Chart::TYPE_PIE);
        $chart->setData([
            'labels' => ['Hôtels', 'Vols'],
            'datasets' => [
                [
                    'backgroundColor' => ['#4ba3a1', '#183c75'], // teal, blue
                    'data' => [$hotels, $flights],
                ],
            ],
        ]);
        $chart->setOptions([
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
            ],
        ]);

        return $this->render('agency/services/index.html.twig', [
            'active_page' => 'services',
            'pagination'  => $pagination,
            'typeChart'   => $chart,
            'stats' => [
                'total'     => count($allServices),
                'hotels'    => $hotels,
                'flights'   => $flights,
                'available' => $available,
            ],
        ]);
    }

    #[Route('/search', name: 'agency_services_search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        return $this->redirectToRoute('agency_services_index', [
            'q' => $request->query->get('q', ''),
        ]);
    }
}
