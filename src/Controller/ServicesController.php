<?php

namespace App\Controller;

use App\Repository\ServicesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


#[Route('/services')]
class ServicesController extends AbstractController
{
    public function __construct(
        private ServicesRepository $servicesRepo,
    ) {}


    #[Route('', name: 'services_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search   = $request->query->get('q', '');
        $services = $search
            ? $this->servicesRepo->findBySearch($search)
            : $this->servicesRepo->findAll();

        return $this->render('services/index.html.twig', [
            'active_page' => 'services',
            'services'    => $services,
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
