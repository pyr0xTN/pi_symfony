<?php

namespace App\Controller;

use App\Repository\ServicesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

#[Route('/services')]
class ServicesController extends AbstractController
{
    public function __construct(
        private ServicesRepository $servicesRepo,
        private ChartBuilderInterface $chartBuilder,
    ) {}

    #[Route('', name: 'services_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search   = $request->query->get('q', '');
        $services = $search
            ? $this->servicesRepo->findBySearch($search)
            : $this->servicesRepo->findAll();

        // ── Stats Calculation ──
        $hotels = 0;
        $flights = 0;
        $available = 0;
        foreach ($services as $s) {
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

        return $this->render('services/index.html.twig', [
            'active_page' => 'services',
            'services'    => $services,
            'typeChart'   => $chart,
            'stats' => [
                'total'     => count($services),
                'hotels'    => $hotels,
                'flights'   => $flights,
                'available' => $available,
            ],
        ]);
    }

    #[Route('/search', name: 'services_search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        return $this->redirectToRoute('services_index', [
            'q' => $request->query->get('q', ''),
        ]);
    }
}
