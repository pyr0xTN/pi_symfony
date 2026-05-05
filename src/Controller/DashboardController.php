<?php

namespace App\Controller;

use App\Repository\ServicesRepository;
use App\Repository\ReservationsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
use Symfony\Component\Routing\Attribute\Route;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/dashboard')]
class DashboardController extends AbstractController
{
    public function __construct(
        private ServicesRepository      $servicesRepo,
        private ReservationsRepository  $reservationsRepo,
        private ChartBuilderInterface   $chartBuilder,
        private PaginatorInterface $paginator,
    ) {}

    
    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function index(Request $request): Response
    {
        
        $filter = $request->query->get('filter', '');
        $search = $request->query->get('q', '');

        if ($filter) {
            $services = $this->servicesRepo->findBy(['type' => $filter]);
        } elseif ($search) {
            $services = $this->servicesRepo->findBySearch($search);
        } else {
            $services = $this->servicesRepo->findAll();
        }

        // Revenue Chart (Last 7 Days)
        $revenueData = $this->reservationsRepo->getRevenueLast7Days();
        
        $revenueLabels = [];
        $revenueValues = [];
        
        // Fill missing days if needed or just use results
        foreach ($revenueData as $data) {
            $revenueLabels[] = $data['date']->format('d M');
            $revenueValues[] = $data['revenue'];
        }

        $revenueChart = $this->chartBuilder->createChart(Chart::TYPE_LINE);
        $revenueChart->setData([
            'labels' => $revenueLabels,
            'datasets' => [
                [
                    'label' => 'Revenue (TND)',
                    'backgroundColor' => 'rgba(45, 206, 137, 0.1)',
                    'borderColor' => '#2dce89',
                    'data' => $revenueValues,
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
        ]);
        $revenueChart->setOptions([
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'grid' => ['display' => false],
                ],
                'x' => [
                    'grid' => ['display' => false],
                ],
            ],
        ]);

        // Service Type Chart
        $typeData = $this->reservationsRepo->getRevenueByType();
        $typeLabels = [];
        $typeValues = [];
        foreach ($typeData as $data) {
            $typeLabels[] = ucfirst($data['type']);
            $typeValues[] = $data['revenue'];
        }

        $typeChart = $this->chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $typeChart->setData([
            'labels' => $typeLabels,
            'datasets' => [
                [
                    'backgroundColor' => ['#2dce89', '#fb6340', '#11cdef', '#f5365c'],
                    'data' => $typeValues,
                ],
            ],
        ]);
        $typeChart->setOptions([
            'maintainAspectRatio' => false,
        ]);

        return $this->render('dashboard/indexservices.html.twig', [
            'active_page'       => 'dashboard',
            'services'          => $services,
            'reservations'      => $this->reservationsRepo->findLatest(10),
            'filter'            => $filter,
            'revenueChart'      => $revenueChart,
            'typeChart'         => $typeChart,
            'hotel_count'=> $this->servicesRepo->count(['type' => 'hotel']),
            'vol_count'=> $this->servicesRepo->count(['type' => 'vol']),
            'reservation_count' => $this->reservationsRepo->count([]),
            'available' => $this->servicesRepo->count(['disponibilite' => true]),
            
        ]);
    }
}
