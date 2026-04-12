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

#[Route('/reservation')]
class ReservationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ReservationsRepository $reservationsRepo,
        private ServicesRepository     $servicesRepo,
    ) {}


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

    
    #[Route('/new', name: 'reservation_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
       
        $serviceId   = $request->query->getInt('serviceId');
        $serviceType = $request->query->get('serviceType', '');
        $serviceDisponibilite=$request->query->getBoolean('disponibilite');

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

           
            if ($serviceType === 'vol') {
                $seatNb = $form->get('siege')->getData();
                
                
                if (empty($seatNb)) {
                    $this->addFlash('error', 'Veuillez sélectionner un siège.');
                    return $this->render('reservation/new.html.twig', [
                        'active_page' => 'reservations',
                        'form'        => $form,
                        'service'     => $service,
                        'serviceType' => $serviceType,
                        'seats'       => $this->buildSeatMap($service),
                    ]);
                }
                
                $reservation->setSeatNb((int) $seatNb);
            } else {
                $reservation->setSeatNb(0); 
            }
           if ($serviceDisponibilite) {
            $this->em->persist($reservation);
            $this->em->flush();
            $this->addFlash('success', 'Réservation créée avec succès !');
            return $this->redirectToRoute('reservations_index');
           }
           else {
            $this->addFlash('danger', 'Service indisponible');
           }
          
            
        }

       
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
    #[Route('/newreservation', name: 'reservation_newfront', methods: ['GET', 'POST'])]
    public function newreservation(Request $request): Response
    {
        
        $serviceId   = $request->query->getInt('serviceId');
        $serviceType = $request->query->get('serviceType', '');
        $serviceDisponibilite=$request->query->getBoolean('disponibilite');

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

            
            if ($serviceType === 'vol') {
                $seatNb = $form->get('siege')->getData();
                
                // Validate that a seat was selected
                if (empty($seatNb)) {
                    $this->addFlash('error', 'Veuillez sélectionner un siège.');
                    return $this->render('reservation/newreservation.html.twig', [
                        'active_page' => 'reservations',
                        'form'        => $form,
                        'service'     => $service,
                        'serviceType' => $serviceType,
                        'seats'       => $this->buildSeatMap($service),
                    ]);
                }
                
                $reservation->setSeatNb((int) $seatNb);
            } else {
                $reservation->setSeatNb(0); 
            }

            if ($serviceDisponibilite) {
                $this->em->persist($reservation);
                $this->em->flush();
                $this->addFlash('success', 'Réservation créée avec succès !');
                return $this->redirectToRoute('ourservices_index');
               }
               else {
                $this->addFlash('danger', 'Service indisponible');
               }
        
        }

       
        $seats = [];
        if ($serviceType === 'vol') {
            $seats = $this->buildSeatMap($service);
        }

        return $this->render('reservation/newreservation.html.twig', [
            'active_page' => 'reservations',
            'form'        => $form,
            'service'     => $service,
            'serviceType' => $serviceType,
            'seats'       => $seats,
        ]);
    }

 
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

   

    #[Route('/{id}/delete', name: 'reservation_delete', methods: ['GET', 'POST'])]
    public function delete(Request $request, int $id): Response
    {
        $reservation = $this->reservationsRepo->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException("Réservation #$id introuvable.");
        }

        if ($request->isMethod('POST')) {
            if ($this->isCsrfTokenValid('delete_resa_' . $id, $request->request->get('_token'))) {
                $this->em->remove($reservation);
                $this->em->flush();
                $this->addFlash('success', 'Réservation supprimée.');
            }
        } else {
            $this->em->remove($reservation);
            $this->em->flush();
            $this->addFlash('success', 'Réservation supprimée.');
        }

        return $this->redirectToRoute('reservations_index');
    }
    #[Route('/{id}/approve', name: 'reservation_approve', methods: ['POST'])]
    public function approve(int $id): Response
    {
        $reservation = $this->reservationsRepo->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException("Réservation #$id introuvable.");
        }
    
        $reservation->setStatut('Confirmée');
        $this->em->flush();
    
        $this->addFlash('success', 'Réservation confirmée.');
        return $this->redirectToRoute('reservations_index');
    }
    #[Route('/by-service/{serviceId}', name: 'reservations_by_service', methods: ['GET'])]
public function byService(int $serviceId): Response
{
    $service = $this->servicesRepo->findOneBy(['idService' => $serviceId]);
    if (!$service) {
        throw $this->createNotFoundException('Service introuvable.');
    }

    $reservations = $this->reservationsRepo->findBy(['idService' => $service]);

    return $this->render('reservation/index.html.twig', [
        'active_page'  => 'reservations',
        'reservations' => $reservations,
    ]);
}
  
    private function buildSeatMap(Services $vol): array
    {
        $capacity = $vol->getCapacite();
 
        
        $takenSeats = array_map(
            fn(Reservations $r) => $r->getSeatNb(),
            $this->reservationsRepo->findBy(['idService' => $vol])
        );
 
        $cols    = 6; 
        $seats   = [];
 
        for ($seatNumber = 1; $seatNumber <= $capacity; $seatNumber++) {
            $seats[] = [
                'number'   => $seatNumber,
                'occupied' => in_array($seatNumber, $takenSeats, true),
            ];
        }
 
        return $seats;
    }

}