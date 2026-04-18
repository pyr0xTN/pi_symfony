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
use Knp\Component\Pager\PaginatorInterface;

#[Route('/myreservations')]
class MyReservationsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ReservationsRepository $reservationsRepo,
        private ServicesRepository     $servicesRepo,
        private PaginatorInterface     $paginator,
    ) {}

    #[Route('', name: 'myreservations_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search       = $request->query->get('q', '');
        $qb           = $this->reservationsRepo->findAllQueryBuilder($search);

        $pagination = $this->paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            8 // items per page
        );

        return $this->render('myreservations/index.html.twig', [
            'active_page'  => 'myreservations',
            'pagination'   => $pagination,
        ]);
    }





    #[Route('/{id}/delete', name: 'myreservation_delete', methods: ['GET', 'POST'])]
    public function delete(Request $request, int $id): Response
    {
        $reservation = $this->reservationsRepo->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException("Réservation #$id introuvable.");
        }

      
        if ($request->isMethod('POST')) {
            if ($this->isCsrfTokenValid('delete_resa_' . $id, $request->request->get('_token'))) {
                $reservation->getIdService()->incrementCapacite();
                $this->em->remove($reservation);
                $this->em->flush();
                $this->addFlash('success', 'Réservation supprimée.');
            }
        } else {
          
            $reservation->getIdService()->incrementCapacite();
            $this->em->remove($reservation);
            $this->em->flush();
            $this->addFlash('success', 'Réservation supprimée.');
        }

        return $this->redirectToRoute('myreservations_index');
    }


}