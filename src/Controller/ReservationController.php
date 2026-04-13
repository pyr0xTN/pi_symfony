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
use Dompdf\Dompdf;
use Dompdf\Options;
use Stripe\Stripe;
use Stripe\Charge;
use Stripe\Exception\CardException;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;


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

            // Read availability directly from the entity — never trust the client query param
            if ($service->getDisponibilite()) {
                $this->em->persist($reservation);
                $this->em->flush();

                // Cash: no online payment needed — stay 'En attente', go back to services
                if ($reservation->getModePaiement() === 'cash') {
                    $this->addFlash('success', 'Réservation créée avec succès ! Vous réglerez sur place.');
                    return $this->redirectToRoute('reservations_index');
                }

                // Stripe / PayPal: go to payment page to confirm
                return $this->redirectToRoute('reservation_payment', [
                    'id' => $reservation->getIdReservation(),
                ]);
            } else {
                $this->addFlash('danger', 'Service indisponible.');
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

            // Read availability directly from the entity — never trust the client query param
            if ($service->getDisponibilite()) {
                $this->em->persist($reservation);
                $this->em->flush();
                $this->addFlash('success', 'Réservation créée avec succès !');
                return $this->redirectToRoute('myreservations_index');
            } else {
                $this->addFlash('danger', 'Service indisponible.');
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

#[Route('/{id}/pdf', name: 'reservation_pdf', methods: ['GET'])]
public function pdf(int $id): Response
{
    $reservation = $this->reservationsRepo->find($id);
    if (!$reservation) {
        throw $this->createNotFoundException("Réservation #$id introuvable.");
    }

    // Only confirmed reservations can be printed
    if (strtolower($reservation->getStatut()) !== 'confirmée') {
        $this->addFlash('error', 'Seules les réservations confirmées peuvent être imprimées.');
        return $this->redirectToRoute('reservation_show', ['id' => $id]);
    }

    $options = new Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isRemoteEnabled', true);

    $dompdf = new Dompdf($options);

    $html = $this->renderView('reservation/pdf.html.twig', [
        'reservation' => $reservation,
    ]);

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $filename = 'reservation-' . $reservation->getIdReservation() . '.pdf';

    return new Response(
        $dompdf->output(),
        200,
        [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]
    );
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

// ═══════════════════════════════════════
// PAYMENT PAGE
// ═══════════════════════════════════════

#[Route('/{id}/payment', name: 'reservation_payment', methods: ['GET'])]
// reservation_payment route
public function payment(int $id): Response
{
    $reservation = $this->reservationsRepo->find($id);
    if (!$reservation) {
        throw $this->createNotFoundException("Réservation #$id introuvable.");
    }

    return $this->render('reservation/payment.html.twig', [
        'reservation'      => $reservation,
        'amount'           => $reservation->getIdService()->getPrix(),
        'stripe_pub_key'   => $_ENV['STRIPE_PUBLISHABLE_KEY'],
        'paypal_client_id' => $_ENV['PAYPAL_CLIENT_ID'],
        'modePaiement'     => $reservation->getModePaiement(), // ← ADD THIS
    ]);
}

// ═══════════════════════════════════════
// STRIPE
// ═══════════════════════════════════════

#[Route('/{id}/payment/stripe', name: 'reservation_pay_stripe', methods: ['POST'])]
public function payStripe(Request $request, int $id): Response
{
    $reservation = $this->reservationsRepo->find($id);
    if (!$reservation) {
        throw $this->createNotFoundException("Réservation #$id introuvable.");
    }

    $stripeToken = $request->request->get('stripeToken');
    $amount      = $reservation->getIdService()->getPrix();

    // Guard: if token is missing the JS failed to generate it (bad key or JS error)
    if (empty($stripeToken)) {
        $this->addFlash('error', 'Erreur : impossible de récupérer les informations de carte. Vérifiez votre connexion et réessayez.');
        return $this->redirectToRoute('reservation_payment', ['id' => $id]);
    }

    Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

    try {
        Charge::create([
            'amount'      => (int)($amount * 100), // in cents
            'currency'    => 'usd',
            'source'      => $stripeToken,
            'description' => 'Reservation #' . $reservation->getIdReservation(),
        ]);

        $reservation->setStatut('Confirmée');
        $this->em->flush();

        $this->addFlash('success', 'Paiement par carte effectué ! Réservation confirmée.');
        return $this->redirectToRoute('reservations_index');

    } catch (CardException $e) {
        // Payment failed — delete the pending reservation to keep the DB clean
        $serviceId = $reservation->getIdService()->getIdService();
        $this->em->remove($reservation);
        $this->em->flush();
        $this->addFlash('error', 'Carte refusée : ' . $e->getMessage() . ' Veuillez réessayer.');
        return $this->redirectToRoute('services_index');
    } catch (\Exception $e) {
        $this->addFlash('error', 'Erreur Stripe : ' . $e->getMessage());
        return $this->redirectToRoute('reservation_payment', ['id' => $id]);
    }
}

// ═══════════════════════════════════════
// PAYPAL — Create Order
// ═══════════════════════════════════════

private function getPaypalClient(): PayPalHttpClient
{
    $clientId     = $_ENV['PAYPAL_CLIENT_ID'];
    $clientSecret = $_ENV['PAYPAL_CLIENT_SECRET'];

    $environment = $_ENV['PAYPAL_MODE'] === 'sandbox'
        ? new SandboxEnvironment($clientId, $clientSecret)
        : new ProductionEnvironment($clientId, $clientSecret);

    return new PayPalHttpClient($environment);
}
#[Route('/{id}/payment/paypal/create', name: 'reservation_paypal_create', methods: ['POST'])]
public function paypalCreate(int $id): Response
{
    $reservation = $this->reservationsRepo->find($id);
    if (!$reservation) {
        throw $this->createNotFoundException("Réservation #$id introuvable.");
    }

    $amount = number_format($reservation->getIdService()->getPrix(), 2, '.', '');
    $client = $this->getPaypalClient();

    $request = new OrdersCreateRequest();
    $request->prefer('return=representation');
    $request->body = [
        'intent'         => 'CAPTURE',
        'purchase_units' => [
            [
                'amount'      => [
                    'currency_code' => 'USD',
                    'value'         => $amount,
                ],
                'description' => 'Reservation #' . $reservation->getIdReservation(),
            ]
        ],
        'application_context' => [
            'return_url' => $this->generateUrl(
                'reservation_paypal_success',
                ['id' => $id],
                \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'cancel_url' => $this->generateUrl(
                'reservation_paypal_cancel',
                ['id' => $id],
                \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL
            ),
        ],
    ];

    try {
        $response = $client->execute($request);
        $order    = $response->result;

        foreach ($order->links as $link) {
            if ($link->rel === 'approve') {
                return $this->redirect($link->href);
            }
        }

        throw new \Exception('No approval link found.');

    } catch (\Exception $e) {
        $this->addFlash('error', 'Erreur PayPal : ' . $e->getMessage());
        return $this->redirectToRoute('reservation_payment', ['id' => $id]);
    }
}

// ═══════════════════════════════════════
// PAYPAL — Capture after approval
// ═══════════════════════════════════════

#[Route('/{id}/payment/paypal/success', name: 'reservation_paypal_success', methods: ['GET'])]
public function paypalSuccess(Request $request, int $id): Response
{
    $reservation = $this->reservationsRepo->find($id);
    if (!$reservation) {
        throw $this->createNotFoundException("Réservation #$id introuvable.");
    }

    $orderId        = $request->query->get('token');
    $client         = $this->getPaypalClient();
    $captureRequest = new OrdersCaptureRequest($orderId);
    $captureRequest->prefer('return=representation');

    try {
        $response = $client->execute($captureRequest);

        if ($response->result->status === 'COMPLETED') {
            $reservation->setStatut('Confirmée');
            $this->em->flush();

            $this->addFlash('success', 'Paiement PayPal effectué ! Réservation confirmée.');
            return $this->redirectToRoute('reservations_index');
        }

        throw new \Exception('Payment not completed.');

    } catch (\Exception $e) {
        $this->addFlash('error', 'Erreur PayPal : ' . $e->getMessage());
        return $this->redirectToRoute('reservation_payment', ['id' => $id]);
    }
}
#[Route('/{id}/payment/paypal/cancel', name: 'reservation_paypal_cancel', methods: ['GET'])]
public function paypalCancel(int $id): Response
{
    // Delete the pending reservation so the DB stays clean on cancellation
    $reservation = $this->reservationsRepo->find($id);
    if ($reservation && $reservation->getStatut() === 'En attente') {
        $this->em->remove($reservation);
        $this->em->flush();
    }
    $this->addFlash('error', 'Paiement PayPal annulé. Votre réservation a été supprimée.');
    return $this->redirectToRoute('services_index');
}
}
