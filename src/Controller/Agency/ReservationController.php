<?php

namespace App\Controller\Agency;

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
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/agency/reservation')]
class ReservationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ReservationsRepository $reservationsRepo,
        private ServicesRepository     $servicesRepo,
        private ChartBuilderInterface  $chartBuilder,
        private PaginatorInterface     $paginator,
    ) {}

    #[Route('', name: 'agency_reservation_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search       = $request->query->get('q', '');
        $qb           = $this->reservationsRepo->findAllQueryBuilder($search);

        $pagination = $this->paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            8 // items per page
        );

        // For charts, we need all reservations
        $allReservations = $search
            ? $this->reservationsRepo->findBySearch($search)
            : $this->reservationsRepo->findAll();

        // ── Stats Calculation ──
        $conf  = 0;
        $pend  = 0;
        $canc  = 0;
        foreach ($allReservations as $r) {
            $st = strtolower($r->getStatut());
            if ($st === 'confirmée' || $st === 'confirmed') $conf++;
            elseif ($st === 'en attente' || $st === 'pending') $pend++;
            elseif ($st === 'annulée' || $st === 'cancelled') $canc++;
        }

        // ── Status Chart ──
        $chart = $this->chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $chart->setData([
            'labels' => ['Confirmée', 'En attente', 'Annulée'],
            'datasets' => [
                [
                    'backgroundColor' => ['#4ba3a1', '#f6c750', '#ff6b6b'], // teal, yellow, red
                    'data' => [$conf, $pend, $canc],
                ],
            ],
        ]);
        $chart->setOptions([
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
            ],
        ]);

        return $this->render('agency/reservation/index.html.twig', [
            'active_page'  => 'reservations',
            'pagination'   => $pagination,
            'statusChart'  => $chart,
            'stats' => [
                'total' => count($allReservations),
                'conf'  => $conf,
                'pend'  => $pend,
                'canc'  => $canc,
            ],
        ]);
    }

    #[Route('/new', name: 'agency_reservation_new', methods: ['GET', 'POST'])]
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
                    return $this->render('agency/reservation/new.html.twig', [
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

            if ($service->getDisponibilite()) {
                $service->decrementCapacite();
                $this->em->persist($reservation);
                $this->em->flush();

                if ($reservation->getModePaiement() === 'cash') {
                    $this->addFlash('success', 'Réservation créée avec succès !');
                    return $this->redirectToRoute('agency_reservation_index');
                }

                return $this->redirectToRoute('agency_reservation_payment', [
                    'id'     => $reservation->getIdReservation(),
                    'origin' => 'back',
                ]);
            } else {
                $this->addFlash('danger', 'Service indisponible.');
            }
        }

        $seats = [];
        if ($serviceType === 'vol') {
            $seats = $this->buildSeatMap($service);
        }

        return $this->render('agency/reservation/new.html.twig', [
            'active_page' => 'reservations',
            'form'        => $form,
            'service'     => $service,
            'serviceType' => $serviceType,
            'seats'       => $seats,
        ]);
    }

    #[Route('/{id}', name: 'agency_reservation_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $reservation = $this->reservationsRepo->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException("Réservation #$id introuvable.");
        }

        return $this->render('agency/reservation/show.html.twig', [
            'active_page' => 'reservations',
            'reservation' => $reservation,
        ]);
    }

    #[Route('/{id}/delete', name: 'agency_reservation_delete', methods: ['GET', 'POST'])]
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

        return $this->redirectToRoute('agency_reservation_index');
    }

    #[Route('/{id}/approve', name: 'agency_reservation_approve', methods: ['POST'])]
    public function approve(int $id): Response
    {
        $reservation = $this->reservationsRepo->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException("Réservation #$id introuvable.");
        }
    
        $reservation->setStatut('Confirmée');
        $this->em->flush();
    
        $this->addFlash('success', 'Réservation confirmée.');
        return $this->redirectToRoute('agency_reservation_index');
    }

    #[Route('/{id}/pdf', name: 'agency_reservation_pdf', methods: ['GET'])]
    public function pdf(int $id): Response
    {
        $reservation = $this->reservationsRepo->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException("Réservation #$id introuvable.");
        }

        if (strtolower($reservation->getStatut()) !== 'confirmée' && strtolower($reservation->getStatut()) !== 'confirmed') {
            $this->addFlash('error', 'Seules les réservations confirmées peuvent être imprimées.');
            return $this->redirectToRoute('agency_reservation_show', ['id' => $id]);
        }

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);

        $html = $this->renderView('agency/reservation/pdf.html.twig', [
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
        $reservations = $vol->getReservationss();
        $capacity     = $vol->getCapacite() + count($reservations);
 
        $takenSeats = array_map(
            fn(Reservations $r) => $r->getSeatNb(),
            $reservations->toArray()
        );
 
        $seats   = [];
        for ($seatNumber = 1; $seatNumber <= $capacity; $seatNumber++) {
            $seats[] = [
                'number'   => $seatNumber,
                'occupied' => in_array($seatNumber, $takenSeats, true),
            ];
        }
        return $seats;
    }

    #[Route('/{id}/payment', name: 'agency_reservation_payment', methods: ['GET'])]
    public function payment(Request $request, int $id): Response
    {
        $reservation = $this->reservationsRepo->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException("Réservation #$id introuvable.");
        }

        $origin = $request->query->get('origin', 'back');

        return $this->render('agency/reservation/payment.html.twig', [
            'reservation'      => $reservation,
            'amount'           => $reservation->getIdService()->getPrix(),
            'stripe_pub_key'   => $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? '',
            'paypal_client_id' => $_ENV['PAYPAL_CLIENT_ID'] ?? '',
            'modePaiement'     => $reservation->getModePaiement(),
            'origin'           => $origin,
        ]);
    }

    #[Route('/{id}/payment/stripe', name: 'agency_reservation_pay_stripe', methods: ['POST'])]
    public function payStripe(Request $request, int $id): Response
    {
        $reservation = $this->reservationsRepo->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException("Réservation #$id introuvable.");
        }

        $origin      = $request->query->get('origin', 'back');
        $stripeToken = $request->request->get('stripeToken');
        $amount      = $reservation->getIdService()->getPrix();

        if (empty($stripeToken)) {
            $this->addFlash('error', 'Erreur : Token Stripe manquant.');
            return $this->redirectToRoute('agency_reservation_payment', ['id' => $id, 'origin' => $origin]);
        }

        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        try {
            Charge::create([
                'amount'      => (int)($amount * 100),
                'currency'    => 'usd',
                'source'      => $stripeToken,
                'description' => 'Agency Reservation #' . $reservation->getIdReservation(),
            ]);

            $reservation->setStatut('Confirmée');
            $this->em->flush();

            $this->addFlash('success', 'Paiement effectué ! Réservation confirmée.');
            return $this->redirectToRoute('agency_reservations_index');

        } catch (CardException $e) {
            $reservation->getIdService()->incrementCapacite();
            $this->em->remove($reservation);
            $this->em->flush();
            $this->addFlash('error', 'Carte refusée : ' . $e->getMessage());
            return $this->redirectToRoute('agency_services_index');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur Stripe : ' . $e->getMessage());
            return $this->redirectToRoute('agency_reservation_payment', ['id' => $id, 'origin' => $origin]);
        }
    }

    private function getPaypalClient(): PayPalHttpClient
    {
        $clientId     = $_ENV['PAYPAL_CLIENT_ID'];
        $clientSecret = $_ENV['PAYPAL_CLIENT_SECRET'];

        $environment = ($_ENV['PAYPAL_MODE'] ?? 'sandbox') === 'sandbox'
            ? new SandboxEnvironment($clientId, $clientSecret)
            : new ProductionEnvironment($clientId, $clientSecret);

        return new PayPalHttpClient($environment);
    }

    #[Route('/{id}/payment/paypal/create', name: 'agency_reservation_paypal_create', methods: ['POST'])]
    public function paypalCreate(Request $request, int $id): Response
    {
        $reservation = $this->reservationsRepo->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException("Réservation #$id introuvable.");
        }

        $origin = $request->query->get('origin', 'back');
        $amount = number_format($reservation->getIdService()->getPrix(), 2, '.', '');
        $client = $this->getPaypalClient();

        $paypalRequest = new OrdersCreateRequest();
        $paypalRequest->prefer('return=representation');
        $paypalRequest->body = [
            'intent'         => 'CAPTURE',
            'purchase_units' => [
                [
                    'amount'      => [
                        'currency_code' => 'USD',
                        'value'         => $amount,
                    ],
                    'description' => 'Agency Reservation #' . $reservation->getIdReservation(),
                ]
            ],
            'application_context' => [
                'return_url' => $this->generateUrl(
                    'agency_reservation_paypal_success',
                    ['id' => $id, 'origin' => $origin],
                    \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL
                ),
                'cancel_url' => $this->generateUrl(
                    'agency_reservation_paypal_cancel',
                    ['id' => $id, 'origin' => $origin],
                    \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL
                ),
            ],
        ];

        try {
            $response = $client->execute($paypalRequest);
            foreach ($response->result->links as $link) {
                if ($link->rel === 'approve') {
                    return $this->redirect($link->href);
                }
            }
            throw new \Exception('No approval link found.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur PayPal : ' . $e->getMessage());
            return $this->redirectToRoute('agency_reservation_payment', ['id' => $id, 'origin' => $origin]);
        }
    }

    #[Route('/{id}/payment/paypal/success', name: 'agency_reservation_paypal_success', methods: ['GET'])]
    public function paypalSuccess(Request $request, int $id): Response
    {
        $reservation = $this->reservationsRepo->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException("Réservation #$id introuvable.");
        }

        $orderId        = $request->query->get('token');
        $client         = $this->getPaypalClient();
        $captureRequest = new OrdersCaptureRequest($orderId);

        try {
            $response = $client->execute($captureRequest);
            if ($response->result->status === 'COMPLETED') {
                $reservation->setStatut('Confirmée');
                $this->em->flush();
                $this->addFlash('success', 'Paiement PayPal effectué !');
                return $this->redirectToRoute('agency_reservation_index');
            }
            throw new \Exception('Payment not completed.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur PayPal : ' . $e->getMessage());
            return $this->redirectToRoute('agency_reservation_payment', ['id' => $id]);
        }
    }

    #[Route('/{id}/payment/paypal/cancel', name: 'agency_reservation_paypal_cancel', methods: ['GET'])]
    public function paypalCancel(int $id): Response
    {
        $reservation = $this->reservationsRepo->find($id);
        if ($reservation && $reservation->getStatut() === 'En attente') {
            $reservation->getIdService()->incrementCapacite();
            $this->em->remove($reservation);
            $this->em->flush();
        }
        $this->addFlash('error', 'Paiement PayPal annulé.');
        return $this->redirectToRoute('agency_services_index');
    }
}
