<?php

namespace App\Controller;

use App\Repository\ServicesRepository;
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
    ) {}


    #[Route('', name: 'ourservices_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search   = $request->query->get('q', '');
        $qb       = $this->servicesRepo->findAllQueryBuilder($search);

        $pagination = $this->paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            8 // items per page
        );

        return $this->render('ourservices/index.html.twig', [
            'active_page' => 'ourservices',
            'pagination'  => $pagination,
        ]);
    }


}
