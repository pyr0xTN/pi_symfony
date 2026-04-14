<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ActivitiesCheckoutController extends AbstractController
{
    private const STRIPE_REQUEST_TIMEOUT_SECONDS = 6;

    public function __construct(private readonly Connection $connection)
    {
    }

    #[Route('/activities/create-checkout-session', name: 'app_activities_create_checkout_session', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createActivitiesCheckoutSession(Request $request, LoggerInterface $logger): JsonResponse
    {
        if (!$this->isCsrfTokenValid('activities-checkout', (string) $request->request->get('_token'))) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid request token.',
            ], 400);
        }

        $activityId = (int) $request->request->get('activity_id', 0);
        $quantity = (int) $request->request->get('quantity', 1);

        if ($activityId <= 0) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid activity selection.',
            ], 400);
        }

        if ($quantity < 1) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid quantity.',
            ], 400);
        }

        $secretKey = (string) ($_SERVER['STRIPE_SECRET_KEY'] ?? $_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY') ?: '');
        $publishableKey = (string) ($_SERVER['STRIPE_PUBLIC_KEY'] ?? $_ENV['STRIPE_PUBLIC_KEY'] ?? getenv('STRIPE_PUBLIC_KEY') ?: '');

        if ($secretKey === '' || $publishableKey === '') {
            return $this->json([
                'success' => false,
                'message' => 'Stripe keys are missing. Please configure STRIPE_SECRET_KEY and STRIPE_PUBLIC_KEY.',
            ], 500);
        }

        $activity = $this->connection->fetchAssociative(
            'SELECT idActivite, titre, prix, placesDisponibles, dateActivite FROM activite WHERE idActivite = ?',
            [$activityId]
        );

        if (!is_array($activity)) {
            return $this->json([
                'success' => false,
                'message' => 'Activity not found.',
            ], 404);
        }

        $availablePlaces = (int) ($activity['placesDisponibles'] ?? 0);
        if ($quantity > $availablePlaces) {
            return $this->json([
                'success' => false,
                'message' => 'Not enough places available for this activity.',
            ], 409);
        }

        try {
            $activityDate = new \DateTimeImmutable((string) ($activity['dateActivite'] ?? ''));
            if ($activityDate <= new \DateTimeImmutable('now')) {
                return $this->json([
                    'success' => false,
                    'message' => 'This activity has passed and can no longer be reserved.',
                ], 409);
            }
        } catch (\Exception) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid activity date.',
            ], 400);
        }

        $unitAmount = (int) ($activity['prix'] ?? 0);
        if ($unitAmount <= 0) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid activity price.',
            ], 400);
        }

        $user = $this->getUser();
        $customerEmail = $user instanceof User ? trim((string) $user->getEmail()) : '';
        $customerId = $user instanceof User ? (int) $user->getId() : 0;
        if ($customerId <= 0) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid authenticated user.',
            ], 403);
        }

        $activityName = trim((string) ($activity['titre'] ?? 'Activity reservation'));
        $totalAmountCents = $unitAmount * $quantity * 100;
        $payload = [
            'amount' => $totalAmountCents,
            'currency' => 'eur',
            'description' => 'Activity reservation: ' . $activityName,
            'automatic_payment_methods[enabled]' => 'true',
            'metadata[activity_id]' => (string) $activityId,
            'metadata[activity_name]' => $activityName,
            'metadata[quantity]' => (string) $quantity,
            'metadata[unit_amount]' => (string) $unitAmount,
            'metadata[user_id]' => (string) $customerId,
        ];

        if ($customerEmail !== '') {
            $payload['receipt_email'] = $customerEmail;
        }

        try {
            $startedAt = microtime(true);
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'timeout' => self::STRIPE_REQUEST_TIMEOUT_SECONDS,
                    'header' => "Authorization: Bearer {$secretKey}\r\n"
                        . "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content' => http_build_query($payload),
                ],
            ]);

            $raw = @file_get_contents('https://api.stripe.com/v1/payment_intents', false, $context);
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            if ($raw === false) {
                $logger->warning('Stripe payment intent creation failed', [
                    'duration_ms' => $durationMs,
                    'quantity' => $quantity,
                    'amount' => $unitAmount,
                    'activity_id' => $activityId,
                ]);

                return $this->json([
                    'success' => false,
                    'message' => 'Unable to initialize payment. Please retry.',
                ], 502);
            }

            $logger->info('Stripe payment intent created', [
                'duration_ms' => $durationMs,
                'quantity' => $quantity,
                'amount' => $unitAmount,
                'activity_id' => $activityId,
            ]);

            $decoded = json_decode($raw, true);
            if (!is_array($decoded) || empty($decoded['client_secret'])) {
                return $this->json([
                    'success' => false,
                    'message' => 'Stripe returned an invalid payment intent response.',
                ], 502);
            }

            return $this->json([
                'success' => true,
                'clientSecret' => $decoded['client_secret'],
                'publishableKey' => $publishableKey,
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => 'Stripe payment is unavailable right now. Please retry.',
                'debug' => $this->getParameter('kernel.environment') === 'dev' ? $e->getMessage() : null,
            ], 502);
        }
    }

    #[Route('/activities/finalize-payment', name: 'app_activities_finalize_payment', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function finalizePayment(Request $request, LoggerInterface $logger): JsonResponse
    {
        if (!$this->isCsrfTokenValid('activities-checkout', (string) $request->request->get('_token'))) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid request token.',
            ], 400);
        }

        $paymentIntentId = trim((string) $request->request->get('payment_intent_id', ''));
        if ($paymentIntentId === '') {
            return $this->json([
                'success' => false,
                'message' => 'Missing payment reference.',
            ], 400);
        }

        $secretKey = (string) ($_SERVER['STRIPE_SECRET_KEY'] ?? $_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY') ?: '');
        if ($secretKey === '') {
            return $this->json([
                'success' => false,
                'message' => 'Stripe secret key is missing. Please configure STRIPE_SECRET_KEY.',
            ], 500);
        }

        $stripePaymentIntent = $this->fetchStripePaymentIntent($paymentIntentId, $secretKey);
        if (!is_array($stripePaymentIntent)) {
            return $this->json([
                'success' => false,
                'message' => 'Unable to verify the payment with Stripe.',
            ], 502);
        }

        if (($stripePaymentIntent['status'] ?? '') !== 'succeeded') {
            return $this->json([
                'success' => false,
                'message' => 'Payment is not completed yet.',
            ], 409);
        }

        $metadata = is_array($stripePaymentIntent['metadata'] ?? null) ? $stripePaymentIntent['metadata'] : [];
        $activityId = (int) ($metadata['activity_id'] ?? 0);
        $quantity = (int) ($metadata['quantity'] ?? 1);
        $unitAmount = (int) ($metadata['unit_amount'] ?? 0);
        $metadataUserId = (int) ($metadata['user_id'] ?? 0);

        $currentUser = $this->getUser();
        $currentUserId = $currentUser instanceof User ? (int) $currentUser->getId() : 0;

        if ($activityId <= 0 || $quantity < 1 || $unitAmount <= 0 || $metadataUserId <= 0 || $currentUserId <= 0) {
            return $this->json([
                'success' => false,
                'message' => 'Payment metadata is incomplete.',
            ], 400);
        }

        if ($metadataUserId !== $currentUserId) {
            return $this->json([
                'success' => false,
                'message' => 'Payment does not belong to the current user.',
            ], 403);
        }

        $expectedAmount = $unitAmount * $quantity * 100;
        $amountReceived = (int) ($stripePaymentIntent['amount_received'] ?? $stripePaymentIntent['amount'] ?? 0);
        if ($amountReceived !== $expectedAmount) {
            return $this->json([
                'success' => false,
                'message' => 'Payment amount mismatch.',
            ], 409);
        }

        try {
            $result = $this->connection->transactional(function (Connection $connection) use ($activityId, $quantity, $unitAmount, $paymentIntentId, $currentUserId) {
                $existingPurchase = $connection->fetchAssociative(
                    'SELECT idAchat FROM achat WHERE stripePaymentIntentId = ? LIMIT 1',
                    [$paymentIntentId]
                );

                if (is_array($existingPurchase)) {
                    return [
                        'alreadySaved' => true,
                        'purchaseId' => (int) ($existingPurchase['idAchat'] ?? 0),
                    ];
                }

                $activity = $connection->fetchAssociative(
                    'SELECT idActivite, placesDisponibles, dateActivite FROM activite WHERE idActivite = ? FOR UPDATE',
                    [$activityId]
                );

                if (!is_array($activity)) {
                    throw new \RuntimeException('Activity not found.');
                }

                $availablePlaces = (int) ($activity['placesDisponibles'] ?? 0);
                if ($availablePlaces < $quantity) {
                    throw new \RuntimeException('Not enough places available for this activity.');
                }

                try {
                    $activityDate = new \DateTimeImmutable((string) ($activity['dateActivite'] ?? ''));
                } catch (\Exception) {
                    throw new \RuntimeException('Invalid activity date.');
                }

                if ($activityDate <= new \DateTimeImmutable('now')) {
                    throw new \RuntimeException('This activity has passed and can no longer be reserved.');
                }

                $connection->executeStatement(
                    'INSERT INTO achat (dateAchat, montantTotal, statut, idClient, nbPlaces, idActivite, stripePaymentIntentId)
                     VALUES (NOW(), ?, ?, ?, ?, ?, ?)',
                    [$unitAmount * $quantity, 'Payé', $currentUserId, $quantity, $activityId, $paymentIntentId]
                );

                $purchaseId = (int) $connection->lastInsertId();

                $connection->executeStatement(
                    'UPDATE activite SET placesDisponibles = placesDisponibles - ? WHERE idActivite = ?',
                    [$quantity, $activityId]
                );

                return [
                    'alreadySaved' => false,
                    'purchaseId' => $purchaseId,
                ];
            });

            $logger->info('Activity payment finalized', [
                'payment_intent_id' => $paymentIntentId,
                'activity_id' => $activityId,
                'quantity' => $quantity,
                'already_saved' => $result['alreadySaved'] ?? false,
                'purchase_id' => $result['purchaseId'] ?? null,
            ]);

            return $this->json([
                'success' => true,
                'alreadySaved' => (bool) ($result['alreadySaved'] ?? false),
                'purchaseId' => (int) ($result['purchaseId'] ?? 0),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            $existingPurchase = $this->connection->fetchAssociative(
                'SELECT idAchat FROM achat WHERE stripePaymentIntentId = ? LIMIT 1',
                [$paymentIntentId]
            );

            return $this->json([
                'success' => true,
                'alreadySaved' => true,
                'purchaseId' => (int) ($existingPurchase['idAchat'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
                'debug' => $this->getParameter('kernel.environment') === 'dev' ? $e->getMessage() : null,
            ], 500);
        }
    }

    private function fetchStripePaymentIntent(string $paymentIntentId, string $secretKey): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => self::STRIPE_REQUEST_TIMEOUT_SECONDS,
                'header' => "Authorization: Bearer {$secretKey}\r\n",
            ],
        ]);

        $raw = @file_get_contents('https://api.stripe.com/v1/payment_intents/' . rawurlencode($paymentIntentId), false, $context);
        if ($raw === false) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}
