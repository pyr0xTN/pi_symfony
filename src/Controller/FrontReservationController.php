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
class FrontReservationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ReservationsRepository $reservationsRepo,
        private ServicesRepository     $servicesRepo,
    ) {}

    #[Route('/new', name: 'reservation_new_front', methods: ['GET', 'POST'])]
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

        // Autofill name from logged-in user
        $user = $this->getUser();
        if ($user instanceof \App\Entity\User) {
            $reservation->setNom($user->getFullName());
        }

        // Autofill date from flight departure if applicable
        if ($serviceType === 'vol' && $service->getDateDepart()) {
            $reservation->setDateReservation($service->getDateDepart());
        } else {
            $reservation->setDateReservation(new \DateTime());
        }

        $form = $this->createForm(ReservationType::class, $reservation, [
            'service_type' => $serviceType,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ($serviceType === 'vol') {
                $seatNb = $form->get('siege')->getData();
                if (empty($seatNb)) {
                    $this->addFlash('error', 'Veuillez sélectionner un siège.');
                    return $this->render('reservation/reservationfront.html.twig', [
                        'active_page' => 'ourservices',
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
                    $this->addFlash('success', 'Réservation créée avec succès ! Vous réglerez sur place.');
                    return $this->redirectToRoute('myreservations_index');
                }

                return $this->redirectToRoute('reservation_payment_front', [
                    'id'     => $reservation->getIdReservation(),
                    'origin' => 'front',
                ]);
            } else {
                $this->addFlash('danger', 'Service indisponible.');
                return $this->redirectToRoute('ourservices_index');
            }
        }

        $seats = [];
        if ($serviceType === 'vol') {
            $seats = $this->buildSeatMap($service);
        }

        return $this->render('reservation/reservationfront.html.twig', [
            'active_page' => 'ourservices',
            'form'        => $form,
            'service'     => $service,
            'serviceType' => $serviceType,
            'seats'       => $seats,
        ]);
    }

    #[Route('/{id}/payment', name: 'reservation_payment_front', methods: ['GET'])]
    public function payment(Request $request, int $id): Response
    {
        $reservation = $this->reservationsRepo->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException("Réservation #$id introuvable.");
        }

        return $this->render('reservation/payment.html.twig', [
            'reservation'      => $reservation,
            'amount'           => $reservation->getIdService()->getPrix(),
            'stripe_pub_key'   => $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? '',
            'paypal_client_id' => $_ENV['PAYPAL_CLIENT_ID'] ?? '',
            'modePaiement'     => $reservation->getModePaiement(),
            'origin'           => 'front',
        ]);
    }

    #[Route('/{id}/payment/stripe', name: 'reservation_pay_stripe_front', methods: ['POST'])]
    public function payStripe(Request $request, int $id): Response
    {
        $reservation = $this->reservationsRepo->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException("Réservation #$id introuvable.");
        }

        $stripeToken = $request->request->get('stripeToken');
        $amount      = $reservation->getIdService()->getPrix();

        if (empty($stripeToken)) {
            $this->addFlash('error', 'Erreur : Token Stripe manquant.');
            return $this->redirectToRoute('reservation_payment_front', ['id' => $id]);
        }

        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        try {
            Charge::create([
                'amount'      => (int)($amount * 100),
                'currency'    => 'usd',
                'source'      => $stripeToken,
                'description' => 'User Reservation #' . $reservation->getIdReservation(),
            ]);

            $reservation->setStatut('Confirmée');
            $this->em->flush();

            $this->addFlash('success', 'Paiement effectué ! Réservation confirmée.');
            return $this->redirectToRoute('myreservations_index');

        } catch (CardException $e) {
            $reservation->getIdService()->incrementCapacite();
            $this->em->remove($reservation);
            $this->em->flush();
            $this->addFlash('error', 'Carte refusée : ' . $e->getMessage());
            return $this->redirectToRoute('ourservices_index');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur Stripe : ' . $e->getMessage());
            return $this->redirectToRoute('reservation_payment_front', ['id' => $id]);
        }
    }

    #[Route('/{id}/pdf', name: 'reservation_pdf_front', methods: ['GET'])]
    public function pdf(int $id): Response
    {
        $reservation = $this->reservationsRepo->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException("Réservation #$id introuvable.");
        }

        if (strtolower($reservation->getStatut()) !== 'confirmée' && strtolower($reservation->getStatut()) !== 'confirmed') {
            $this->addFlash('error', 'Seules les réservations confirmées peuvent être imprimées.');
            return $this->redirectToRoute('myreservations_index');
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
        $reservations = $vol->getReservationss();
        $capacity     = $vol->getCapacite() + count($reservations);
        $takenSeats = array_map(fn($r) => $r->getSeatNb(), $reservations->toArray());
        $seats = [];
        for ($i = 1; $i <= $capacity; $i++) {
            $seats[] = ['number' => $i, 'occupied' => in_array($i, $takenSeats, true)];
        }
        return $seats;
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

    #[Route('/{id}/payment/paypal/create', name: 'reservation_paypal_create_front', methods: ['POST'])]
    public function paypalCreate(int $id): Response
    {
        $reservation = $this->reservationsRepo->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException("Réservation #$id introuvable.");
        }
        $amount = number_format($reservation->getIdService()->getPrix(), 2, '.', '');
        $client = $this->getPaypalClient();
        $paypalRequest = new OrdersCreateRequest();
        $paypalRequest->prefer('return=representation');
        $paypalRequest->body = [
            'intent'         => 'CAPTURE',
            'purchase_units' => [['amount' => ['currency_code' => 'USD', 'value' => $amount]]],
            'application_context' => [
                'return_url' => $this->generateUrl('reservation_paypal_success_front', ['id' => $id], 0),
                'cancel_url' => $this->generateUrl('reservation_paypal_cancel_front', ['id' => $id], 0),
            ],
        ];
        try {
            $response = $client->execute($paypalRequest);
            foreach ($response->result->links as $link) {
                if ($link->rel === 'approve') return $this->redirect($link->href);
            }
            throw new \Exception('No approval link');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('reservation_payment_front', ['id' => $id]);
        }
    }

    #[Route('/{id}/payment/paypal/success', name: 'reservation_paypal_success_front', methods: ['GET'])]
    public function paypalSuccess(Request $request, int $id): Response
    {
        $reservation = $this->reservationsRepo->find($id);
        $orderId = $request->query->get('token');
        $client = $this->getPaypalClient();
        $captureRequest = new OrdersCaptureRequest($orderId);
        try {
            $response = $client->execute($captureRequest);
            if ($response->result->status === 'COMPLETED') {
                $reservation->setStatut('Confirmée');
                $this->em->flush();
                $this->addFlash('success', 'Paiement PayPal réussi !');
                return $this->redirectToRoute('myreservations_index');
            }
            throw new \Exception('Failed');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('reservation_payment_front', ['id' => $id]);
        }
    }

    #[Route('/{id}/payment/paypal/cancel', name: 'reservation_paypal_cancel_front', methods: ['GET'])]
    public function paypalCancel(int $id): Response
    {
        $reservation = $this->reservationsRepo->find($id);
        if ($reservation && $reservation->getStatut() === 'En attente') {
            $reservation->getIdService()->incrementCapacite();
            $this->em->remove($reservation);
            $this->em->flush();
        }
        $this->addFlash('error', 'Paiement annulé.');
        return $this->redirectToRoute('ourservices_index');
    }
}
