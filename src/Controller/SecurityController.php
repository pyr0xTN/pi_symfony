<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\LoginFormAuthenticator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        // if user is already logged in, redirect to main page
        if ($this->getUser()) {
            return $this->redirectToRoute('app_mainpage');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();
        $initialView = $request->query->get('view', 'login');

        if (!in_array($initialView, ['login', 'signup'], true)) {
            $initialView = 'login';
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'initial_view' => $initialView,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route(path: '/signup', name: 'app_register')]
    public function register(): Response
    {
        return $this->redirectToRoute('app_login', ['view' => 'signup']);
    }

    #[Route(path: '/login/google', name: 'app_login_google', methods: ['GET'])]
    public function loginWithGoogle(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_mainpage');
        }

        $clientId = trim((string) ($_ENV['GOOGLE_OAUTH_CLIENT_ID'] ?? $_SERVER['GOOGLE_OAUTH_CLIENT_ID'] ?? getenv('GOOGLE_OAUTH_CLIENT_ID') ?: ''));
        if ($clientId === '') {
            $this->addFlash('error', 'Google login is not configured yet. Ask admin to set GOOGLE_OAUTH_CLIENT_ID and GOOGLE_OAUTH_CLIENT_SECRET.');
            return $this->redirectToRoute('app_login');
        }

        $state = bin2hex(random_bytes(16));
        $request->getSession()->set('google_oauth_state', $state);

        $redirectUri = $this->generateUrl('connect_google_check', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);

        return $this->redirect('https://accounts.google.com/o/oauth2/v2/auth?' . $query);
    }

    #[Route(path: '/connect/google/check', name: 'connect_google_check', methods: ['GET'])]
    public function loginWithGoogleCallback(
        Request $request,
        HttpClientInterface $httpClient,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserAuthenticatorInterface $userAuthenticator,
        LoginFormAuthenticator $formAuthenticator
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_mainpage');
        }

        $expectedState = (string) $request->getSession()->get('google_oauth_state', '');
        $actualState = (string) $request->query->get('state', '');
        $request->getSession()->remove('google_oauth_state');

        if ($expectedState === '' || $actualState === '' || !hash_equals($expectedState, $actualState)) {
            $this->addFlash('error', 'Google login was rejected (invalid state). Please try again.');
            return $this->redirectToRoute('app_login');
        }

        $authCode = trim((string) $request->query->get('code', ''));
        if ($authCode === '') {
            $this->addFlash('error', 'Google login was canceled or failed.');
            return $this->redirectToRoute('app_login');
        }

        $clientId = trim((string) ($_ENV['GOOGLE_OAUTH_CLIENT_ID'] ?? $_SERVER['GOOGLE_OAUTH_CLIENT_ID'] ?? getenv('GOOGLE_OAUTH_CLIENT_ID') ?: ''));
        $clientSecret = trim((string) ($_ENV['GOOGLE_OAUTH_CLIENT_SECRET'] ?? $_SERVER['GOOGLE_OAUTH_CLIENT_SECRET'] ?? getenv('GOOGLE_OAUTH_CLIENT_SECRET') ?: ''));
        $redirectUri = $this->generateUrl('connect_google_check', [], UrlGeneratorInterface::ABSOLUTE_URL);

        if ($clientId === '' || $clientSecret === '') {
            $this->addFlash('error', 'Google login credentials are missing on server.');
            return $this->redirectToRoute('app_login');
        }

        try {
            $tokenResponse = $httpClient->request('POST', 'https://oauth2.googleapis.com/token', [
                'body' => [
                    'code' => $authCode,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'redirect_uri' => $redirectUri,
                    'grant_type' => 'authorization_code',
                ],
                'timeout' => 12,
            ]);

            $tokenData = $tokenResponse->toArray(false);
            $accessToken = trim((string) ($tokenData['access_token'] ?? ''));
            if ($accessToken === '') {
                throw new \RuntimeException('Missing Google access token');
            }

            $userinfoResponse = $httpClient->request('GET', 'https://www.googleapis.com/oauth2/v3/userinfo', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'timeout' => 12,
            ]);

            $googleUser = $userinfoResponse->toArray(false);
        } catch (\Throwable $exception) {
            $this->addFlash('error', 'Google login failed. Please try again.');
            return $this->redirectToRoute('app_login');
        }

        $email = mb_strtolower(trim((string) ($googleUser['email'] ?? '')));
        $emailVerified = (bool) ($googleUser['email_verified'] ?? false);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$emailVerified) {
            $this->addFlash('error', 'Google account email is not verified or invalid.');
            return $this->redirectToRoute('app_login');
        }

        $user = $userRepository->findByEmail($email);
        if (!$user instanceof User) {
            $givenName = trim((string) ($googleUser['given_name'] ?? ''));
            $familyName = trim((string) ($googleUser['family_name'] ?? ''));
            $fullName = trim((string) ($googleUser['name'] ?? ''));

            if ($givenName === '' && $fullName !== '') {
                $parts = preg_split('/\s+/', $fullName, 2) ?: [];
                $givenName = trim((string) ($parts[0] ?? 'Google'));
                $familyName = trim((string) ($parts[1] ?? 'User'));
            }

            if ($givenName === '') {
                $givenName = 'Google';
            }
            if ($familyName === '') {
                $familyName = 'User';
            }

            $emailLocalPart = strstr($email, '@', true);
            $baseUsername = $this->buildGoogleUsernameBase($emailLocalPart ?: 'google_user');
            $uniqueUsername = $this->buildUniqueUsername($userRepository, $baseUsername);

            $user = new User();
            $user->setEmail($email);
            $user->setName(mb_substr($givenName, 0, 25));
            $user->setLastName(mb_substr($familyName, 0, 25));
            $user->setUsername($uniqueUsername);
            $user->setRole('USER');
            $randomPassword = bin2hex(random_bytes(16));
            $user->setPassword($passwordHasher->hashPassword($user, $randomPassword));

            $entityManager->persist($user);
            $entityManager->flush();
        }

        if ($user->isBlocked()) {
            $this->addFlash('error', 'You got blocked in this site from admin.');
            return $this->redirectToRoute('app_login');
        }

        return $userAuthenticator->authenticateUser($user, $formAuthenticator, $request);
    }

    #[Route(path: '/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request, UserRepository $userRepository, MailerInterface $mailer): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('forgot_password_request', (string) $request->request->get('_csrf_token'))) {
                $this->addFlash('error', 'Invalid reset request. Please try again.');
                return $this->redirectToRoute('app_forgot_password');
            }

            $email = mb_strtolower(trim((string) $request->request->get('email', '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Please enter a valid email address.');
                return $this->redirectToRoute('app_forgot_password');
            }

            $user = $userRepository->findByEmail($email);
            $session = $request->getSession();
            $this->clearPasswordResetState($session);
            $session->set('password_reset_email', $email);

            if ($user instanceof User && $user->getId() !== null) {
                $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expiresAt = new \DateTimeImmutable('+10 minutes');

                $session->set('password_reset_user_id', (int) $user->getId());
                $session->set('password_reset_code_hash', password_hash($code, PASSWORD_DEFAULT));
                $session->set('password_reset_expires_at', $expiresAt->getTimestamp());
                $session->set('password_reset_verified', false);

                try {
                    $mail = (new Email())
                        ->from((string) ($_ENV['EMAIL_FROM'] ?? $_SERVER['EMAIL_FROM'] ?? 'noreply@rehletna.tn'))
                        ->to($email)
                        ->subject('Your password reset code')
                        ->text(
                            "Hello,\n\n"
                            . "Your password reset verification code is: {$code}\n"
                            . "This code expires in 10 minutes.\n\n"
                            . "If you did not request this reset, you can ignore this email."
                        );

                    $mailer->send($mail);
                } catch (\Throwable $exception) {
                    $this->clearPasswordResetState($session);
                    $this->addFlash('error', 'We could not send the reset email right now. Please try again.');
                    return $this->redirectToRoute('app_forgot_password');
                }
            }

            $this->addFlash('success', 'If the email exists, a verification code has been sent.');
            return $this->redirectToRoute('app_forgot_password_verify');
        }

        return $this->render('security/forgot_password.html.twig');
    }

    #[Route(path: '/forgot-password/verify', name: 'app_forgot_password_verify', methods: ['GET', 'POST'])]
    public function forgotPasswordVerify(Request $request): Response
    {
        $session = $request->getSession();
        $email = (string) $session->get('password_reset_email', '');

        if ($email === '') {
            $this->addFlash('error', 'Start by entering your email address.');
            return $this->redirectToRoute('app_forgot_password');
        }

        $expiresAt = (int) $session->get('password_reset_expires_at', 0);
        $expiresIn = $expiresAt > 0 ? max(0, $expiresAt - time()) : null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('forgot_password_verify', (string) $request->request->get('_csrf_token'))) {
                $this->addFlash('error', 'Invalid verification request.');
                return $this->redirectToRoute('app_forgot_password_verify');
            }

            $code = preg_replace('/\D+/', '', (string) $request->request->get('code', ''));
            if (!is_string($code) || strlen($code) !== 6) {
                $this->addFlash('error', 'Please enter a valid 6-digit verification code.');
                return $this->redirectToRoute('app_forgot_password_verify');
            }

            $hashedCode = (string) $session->get('password_reset_code_hash', '');
            if ($hashedCode === '' || $expiresAt < time()) {
                $this->clearPasswordResetState($session);
                $this->addFlash('error', 'Code is missing or expired. Please request a new one.');
                return $this->redirectToRoute('app_forgot_password');
            }

            if (!password_verify($code, $hashedCode)) {
                $this->addFlash('error', 'Invalid verification code.');
                return $this->redirectToRoute('app_forgot_password_verify');
            }

            $session->set('password_reset_verified', true);
            return $this->redirectToRoute('app_forgot_password_reset');
        }

        return $this->render('security/forgot_password_verify.html.twig', [
            'email' => $email,
            'expires_in' => $expiresIn,
        ]);
    }

    #[Route(path: '/forgot-password/reset', name: 'app_forgot_password_reset', methods: ['GET', 'POST'])]
    public function forgotPasswordReset(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $session = $request->getSession();
        $verified = (bool) $session->get('password_reset_verified', false);
        $userId = (int) $session->get('password_reset_user_id', 0);

        if (!$verified || $userId <= 0) {
            $this->addFlash('error', 'Please verify your reset code first.');
            return $this->redirectToRoute('app_forgot_password');
        }

        $user = $userRepository->find($userId);
        if (!$user instanceof User) {
            $this->clearPasswordResetState($session);
            $this->addFlash('error', 'Reset session is no longer valid. Please retry.');
            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('forgot_password_reset', (string) $request->request->get('_csrf_token'))) {
                $this->addFlash('error', 'Invalid password reset request.');
                return $this->redirectToRoute('app_forgot_password_reset');
            }

            $password = (string) $request->request->get('password', '');
            $confirmPassword = (string) $request->request->get('confirm_password', '');

            if (strlen($password) < 6) {
                $this->addFlash('error', 'Password must contain at least 6 characters.');
                return $this->redirectToRoute('app_forgot_password_reset');
            }

            if (!hash_equals($password, $confirmPassword)) {
                $this->addFlash('error', 'Password and confirmation do not match.');
                return $this->redirectToRoute('app_forgot_password_reset');
            }

            $user->setPassword($passwordHasher->hashPassword($user, $password));
            $entityManager->persist($user);
            $entityManager->flush();

            $this->clearPasswordResetState($session);
            $this->addFlash('success', 'Your password has been reset. Please log in.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/forgot_password_reset.html.twig', [
            'email' => (string) $user->getEmail(),
        ]);
    }

    private function clearPasswordResetState(SessionInterface $session): void
    {
        $session->remove('password_reset_email');
        $session->remove('password_reset_user_id');
        $session->remove('password_reset_code_hash');
        $session->remove('password_reset_expires_at');
        $session->remove('password_reset_verified');
    }

    #[Route(path: '/login/face-id', name: 'app_login_face_id_page', methods: ['GET'])]
    public function faceIdLoginPage(): Response
    {
        return $this->render('security/face_login.html.twig', [
            'mode' => 'login',
        ]);
    }

    #[Route(path: '/login/face-id/enroll', name: 'app_face_id_enroll_page', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function faceIdEnrollPage(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/face_login.html.twig', [
            'mode' => 'enroll',
        ]);
    }

    #[Route(path: '/login/face-id', name: 'app_login_face_id', methods: ['POST'])]
    public function loginWithFaceId(
        Request $request,
        Connection $connection,
        UserRepository $userRepository,
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        UserAuthenticatorInterface $userAuthenticator,
        LoginFormAuthenticator $formAuthenticator
    ): Response {
        $this->appendFaceIdAuditLog('request_received', [
            'route' => 'app_login_face_id',
            'content_length' => (int) $request->headers->get('content-length', 0),
            'session_id' => (string) $request->cookies->get('PHPSESSID', ''),
        ]);

        if ($this->getUser() instanceof User) {
            $this->appendFaceIdAuditLog('already_authenticated', [
                'route' => 'app_login_face_id',
            ]);
            return $this->json([
                'success' => true,
                'message' => 'Already logged in.',
                'redirectUrl' => $this->generateUrl('app_mainpage'),
            ]);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $this->appendFaceIdAuditLog('rejected_invalid_payload', [
                'route' => 'app_login_face_id',
            ]);
            $logger->warning('Face ID login rejected: invalid payload.', [
                'route' => 'app_login_face_id',
            ]);
            return $this->json(['success' => false, 'message' => 'Invalid payload.'], Response::HTTP_BAD_REQUEST);
        }

        $imageData = (string) ($payload['imageData'] ?? '');
        $capturedImageBinary = $this->decodeDataUrlImage($imageData);
        if ($capturedImageBinary === null) {
            $this->appendFaceIdAuditLog('rejected_invalid_image', [
                'route' => 'app_login_face_id',
            ]);
            $logger->warning('Face ID login rejected: invalid image data.', [
                'route' => 'app_login_face_id',
            ]);
            return $this->json(['success' => false, 'message' => 'Invalid face image.'], Response::HTTP_BAD_REQUEST);
        }

        $userRows = $connection->executeQuery(
            'SELECT id, face_data FROM `user` WHERE face_data IS NOT NULL'
        )->fetchAllAssociative();

        if (!$userRows) {
            $this->appendFaceIdAuditLog('rejected_no_enrolled_rows', [
                'route' => 'app_login_face_id',
            ]);
            $logger->info('Face ID login rejected: no enrolled face rows.', [
                'route' => 'app_login_face_id',
            ]);
            return $this->json(['success' => false, 'message' => 'No enrolled face data found.'], Response::HTTP_UNAUTHORIZED);
        }

        $faceApiUrl = $this->resolveFaceIdApiUrl();
        $probeEmbedding = null;
        $probeNoFaceDetected = false;

        try {
            $probeEmbedding = $this->extractFaceEmbedding($httpClient, $faceApiUrl, $imageData);
            if ($probeEmbedding === null) {
                $probeNoFaceDetected = true;
            }
        } catch (\RuntimeException $exception) {
            $this->appendFaceIdAuditLog('failed_probe_embedding_api', [
                'route' => 'app_login_face_id',
                'error' => $exception->getMessage(),
            ]);
            $logger->error('Face ID login failed: probe embedding extraction unavailable.', [
                'route' => 'app_login_face_id',
                'error' => $exception->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Face API is unavailable. Start Python Face ID service and retry.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if ($probeEmbedding === null) {
            $this->appendFaceIdAuditLog('rejected_no_probe_face', [
                'route' => 'app_login_face_id',
            ]);
            $logger->info('Face ID login rejected: no probe face detected.', [
                'route' => 'app_login_face_id',
            ]);

            return $this->json([
                'success' => false,
                'message' => 'No clear face detected. Keep only your face in frame with good lighting and retry.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $debugEnabled = (bool) $this->getParameter('kernel.debug');
        $bestSimilarity = 0.0;
        $secondBestSimilarity = 0.0;
        $bestUserId = null;
        $evaluatedUsers = 0;
        $embeddingComparisons = 0;
        $fallbackComparisons = 0;

        foreach ($userRows as $row) {
            $storedFaceBinary = $this->normalizeStoredFaceBinary($row['face_data'] ?? null);
            if ($storedFaceBinary === null) {
                continue;
            }

            $evaluatedUsers++;

            $similarity = null;
            $storedFaceDataUrl = $this->binaryImageToDataUrl($storedFaceBinary);
            if ($storedFaceDataUrl === null) {
                continue;
            }

            try {
                $storedEmbedding = $this->extractFaceEmbedding($httpClient, $faceApiUrl, $storedFaceDataUrl);
                if ($storedEmbedding === null) {
                    continue;
                }

                $similarity = $this->computeEmbeddingSimilarity($probeEmbedding, $storedEmbedding);
                if ($similarity !== null) {
                    $embeddingComparisons++;
                }
            } catch (\RuntimeException $exception) {
                continue;
            }

            if ($similarity === null) {
                continue;
            }

            if ($similarity > $bestSimilarity) {
                $secondBestSimilarity = $bestSimilarity;
                $bestSimilarity = $similarity;
                $bestUserId = isset($row['id']) ? (int) $row['id'] : null;
            } elseif ($similarity > $secondBestSimilarity) {
                $secondBestSimilarity = $similarity;
            }
        }

        if ($embeddingComparisons === 0) {
            $this->appendFaceIdAuditLog('failed_no_comparison_path', [
                'route' => 'app_login_face_id',
            ]);
            return $this->json([
                'success' => false,
                'message' => 'No valid enrolled face template found. Re-enroll your face with a close, clear capture.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $singleEnrolledUser = count($userRows) === 1;
        $usingEmbedding = true;
        $matchThreshold = $singleEnrolledUser ? 0.93 : 0.90;
        $ambiguityGap = 0.08;
        $debug = $debugEnabled ? [
            'bestSimilarity' => round($bestSimilarity, 4),
            'secondBestSimilarity' => round($secondBestSimilarity, 4),
            'matchThreshold' => $matchThreshold,
            'ambiguityGap' => $ambiguityGap,
            'usingEmbedding' => $usingEmbedding,
            'probeEmbeddingUnavailable' => false,
            'probeNoFaceDetected' => $probeNoFaceDetected,
            'enrolledUsers' => count($userRows),
            'evaluatedUsers' => $evaluatedUsers,
            'embeddingComparisons' => $embeddingComparisons,
            'fallbackComparisons' => $fallbackComparisons,
        ] : null;

        // Strict threshold blocks lookalikes and background-only matches.
        if ($bestUserId === null || $bestSimilarity < $matchThreshold) {
            $message = 'Face not recognized. Keep only your face centered with good lighting and retry.';
            $payload = ['success' => false, 'message' => $message];
            if ($debug !== null) {
                $payload['debug'] = $debug;
            }

            $this->appendFaceIdAuditLog('rejected_below_threshold', [
                'route' => 'app_login_face_id',
                'best_similarity' => round($bestSimilarity, 4),
                'second_best_similarity' => round($secondBestSimilarity, 4),
                'threshold' => $matchThreshold,
                'enrolled_users' => count($userRows),
                'evaluated_users' => $evaluatedUsers,
                'embedding_comparisons' => $embeddingComparisons,
                'fallback_comparisons' => $fallbackComparisons,
            ]);

            $logger->info('Face ID login rejected: below threshold.', [
                'route' => 'app_login_face_id',
                'best_similarity' => round($bestSimilarity, 4),
                'threshold' => $matchThreshold,
                'second_best_similarity' => round($secondBestSimilarity, 4),
                'enrolled_users' => count($userRows),
                'evaluated_users' => $evaluatedUsers,
                'embedding_comparisons' => $embeddingComparisons,
                'fallback_comparisons' => $fallbackComparisons,
            ]);

            return $this->json($payload, Response::HTTP_UNAUTHORIZED);
        }

        if ($secondBestSimilarity > 0.0 && ($bestSimilarity - $secondBestSimilarity) < $ambiguityGap) {
            $payload = [
                'success' => false,
                'message' => 'Face match is ambiguous. Please retry with better lighting and angle.',
            ];
            if ($debug !== null) {
                $payload['debug'] = $debug;
            }

            $this->appendFaceIdAuditLog('rejected_ambiguous_match', [
                'route' => 'app_login_face_id',
                'best_similarity' => round($bestSimilarity, 4),
                'second_best_similarity' => round($secondBestSimilarity, 4),
                'ambiguity_gap' => $ambiguityGap,
            ]);

            $logger->info('Face ID login rejected: ambiguous match.', [
                'route' => 'app_login_face_id',
                'best_similarity' => round($bestSimilarity, 4),
                'second_best_similarity' => round($secondBestSimilarity, 4),
                'ambiguity_gap' => $ambiguityGap,
            ]);

            return $this->json($payload, Response::HTTP_UNAUTHORIZED);
        }

        $matchedUser = $userRepository->find($bestUserId);
        if (!$matchedUser instanceof User) {
            $this->appendFaceIdAuditLog('rejected_matched_user_not_found', [
                'route' => 'app_login_face_id',
                'best_user_id' => $bestUserId,
            ]);
            $logger->warning('Face ID login rejected: matched user id not found.', [
                'route' => 'app_login_face_id',
                'best_user_id' => $bestUserId,
            ]);
            return $this->json(['success' => false, 'message' => 'Matched user not found.'], Response::HTTP_UNAUTHORIZED);
        }

        if ($matchedUser->isBlocked()) {
            $this->appendFaceIdAuditLog('rejected_user_blocked', [
                'route' => 'app_login_face_id',
                'best_user_id' => $bestUserId,
            ]);
            $logger->info('Face ID login rejected: matched user is blocked.', [
                'route' => 'app_login_face_id',
                'best_user_id' => $bestUserId,
            ]);
            return $this->json(['success' => false, 'message' => 'You got blocked in this site from admin.'], Response::HTTP_FORBIDDEN);
        }

        $userAuthenticator->authenticateUser($matchedUser, $formAuthenticator, $request);

        $this->appendFaceIdAuditLog('login_success', [
            'route' => 'app_login_face_id',
            'best_user_id' => $bestUserId,
            'best_similarity' => round($bestSimilarity, 4),
            'second_best_similarity' => round($secondBestSimilarity, 4),
            'threshold' => $matchThreshold,
        ]);

        $logger->info('Face ID login success.', [
            'route' => 'app_login_face_id',
            'best_user_id' => $bestUserId,
            'best_similarity' => round($bestSimilarity, 4),
            'second_best_similarity' => round($secondBestSimilarity, 4),
            'threshold' => $matchThreshold,
        ]);

        return $this->json([
            'success' => true,
            'message' => 'Face recognized. Logging in...',
            'redirectUrl' => $this->generateUrl('app_mainpage'),
        ]);
    }

    #[Route(path: '/login/face-id/enroll', name: 'app_face_id_enroll', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function enrollFaceId(
        Request $request,
        EntityManagerInterface $entityManager,
        HttpClientInterface $httpClient
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            return $this->json(['success' => false, 'message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isBlocked()) {
            return $this->json(['success' => false, 'message' => 'You got blocked in this site from admin.'], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['success' => false, 'message' => 'Invalid payload.'], Response::HTTP_BAD_REQUEST);
        }

        $imageData = (string) ($payload['imageData'] ?? '');
        $capturedImageBinary = $this->decodeDataUrlImage($imageData);
        if ($capturedImageBinary === null) {
            return $this->json(['success' => false, 'message' => 'Invalid face image.'], Response::HTTP_BAD_REQUEST);
        }

        $faceApiUrl = $this->resolveFaceIdApiUrl();

        try {
            $faceEmbedding = $this->extractFaceEmbedding($httpClient, $faceApiUrl, $imageData);
        } catch (
            RuntimeException $exception
        ) {
            return $this->json([
                'success' => false,
                'message' => 'Face API is unavailable. Start Python Face ID service and retry.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if ($faceEmbedding === null) {
            return $this->json([
                'success' => false,
                'message' => 'No face detected. Keep your face inside the frame and try again.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user->setFaceData($capturedImageBinary);
        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Face profile saved successfully.',
            'redirectUrl' => $this->generateUrl('app_mainpage'),
        ]);
    }

    #[Route(path: '/login/qr-hash', name: 'app_login_qr_hash', methods: ['POST'])]
    public function loginWithQrHash(
        Request $request,
        UserRepository $userRepository,
        UserAuthenticatorInterface $userAuthenticator,
        LoginFormAuthenticator $formAuthenticator
    ): Response {
        if ($this->getUser() instanceof User) {
            return $this->json([
                'success' => true,
                'message' => 'Already logged in.',
                'redirectUrl' => $this->generateUrl('app_mainpage'),
            ]);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['success' => false, 'message' => 'Invalid payload.'], Response::HTTP_BAD_REQUEST);
        }

        $email = trim((string) ($payload['email'] ?? ''));
        $passwordHash = trim((string) ($payload['password_hash'] ?? ''));

        if ($email === '' || $passwordHash === '') {
            return $this->json(['success' => false, 'message' => 'Missing QR credentials.'], Response::HTTP_BAD_REQUEST);
        }

        $matchedUser = $userRepository->findByEmail($email);
        if (!$matchedUser instanceof User) {
            return $this->json(['success' => false, 'message' => 'Email not found.'], Response::HTTP_UNAUTHORIZED);
        }

        if ($matchedUser->isBlocked()) {
            return $this->json(['success' => false, 'message' => 'You got blocked in this site from admin.'], Response::HTTP_FORBIDDEN);
        }

        $storedHash = (string) $matchedUser->getPassword();
        if ($storedHash === '' || !hash_equals($storedHash, $passwordHash)) {
            return $this->json(['success' => false, 'message' => 'Invalid QR code credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        $userAuthenticator->authenticateUser($matchedUser, $formAuthenticator, $request);

        return $this->json([
            'success' => true,
            'message' => 'QR verified. Logging in...',
            'redirectUrl' => $this->generateUrl('app_mainpage'),
        ]);
    }

    private function decodeDataUrlImage(string $imageData): ?string
    {
        if ($imageData === '' || !str_starts_with($imageData, 'data:image/')) {
            return null;
        }

        $separator = strpos($imageData, ',');
        if ($separator === false) {
            return null;
        }

        $decoded = base64_decode(substr($imageData, $separator + 1), true);
        if (!is_string($decoded) || $decoded === '') {
            return null;
        }

        return $decoded;
    }

    private function buildGoogleUsernameBase(string $seed): string
    {
        $normalized = strtolower(trim($seed));
        $normalized = preg_replace('/[^a-z0-9._-]/', '_', $normalized) ?? 'google_user';
        $normalized = trim($normalized, '._-');
        if ($normalized === '') {
            $normalized = 'google_user';
        }

        return mb_substr($normalized, 0, 18);
    }

    private function buildUniqueUsername(UserRepository $userRepository, string $base): string
    {
        $candidate = $base;
        $suffix = 1;

        while ($userRepository->findOneBy(['username' => $candidate]) instanceof User) {
            $candidate = mb_substr($base, 0, 18) . '_' . $suffix;
            if (mb_strlen($candidate) > 25) {
                $candidate = mb_substr($candidate, 0, 25);
            }
            $suffix++;
        }

        return $candidate;
    }

    private function computeFaceSimilarity(string $firstImageBinary, string $secondImageBinary): ?float
    {
        $firstVector = $this->createGrayVector($firstImageBinary, 40);
        $secondVector = $this->createGrayVector($secondImageBinary, 40);

        if ($firstVector === null || $secondVector === null || count($firstVector) !== count($secondVector)) {
            return null;
        }

        $distanceSum = 0.0;
        $count = count($firstVector);

        for ($index = 0; $index < $count; $index++) {
            $distanceSum += abs($firstVector[$index] - $secondVector[$index]) / 255.0;
        }

        $averageDistance = $count > 0 ? ($distanceSum / $count) : 1.0;
        $similarity = 1.0 - $averageDistance;

        return max(0.0, min(1.0, $similarity));
    }

    private function createGrayVector(string $imageBinary, int $size): ?array
    {
        if (!\function_exists('imagecreatefromstring') || !\function_exists('imagecreatetruecolor') || !\function_exists('imagecopyresampled') || !\function_exists('imagecolorat') || !\function_exists('imagesx') || !\function_exists('imagesy') || !\function_exists('imagedestroy')) {
            return null;
        }

        $source = @\imagecreatefromstring($imageBinary);
        if ($source === false) {
            return null;
        }

        $scaled = \imagecreatetruecolor($size, $size);
        if ($scaled === false) {
            \imagedestroy($source);
            return null;
        }

        \imagecopyresampled(
            $scaled,
            $source,
            0,
            0,
            0,
            0,
            $size,
            $size,
            \imagesx($source),
            \imagesy($source)
        );

        $vector = [];
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $rgb = \imagecolorat($scaled, $x, $y);
                $red = ($rgb >> 16) & 0xFF;
                $green = ($rgb >> 8) & 0xFF;
                $blue = $rgb & 0xFF;

                $gray = (int) round(($red * 0.299) + ($green * 0.587) + ($blue * 0.114));
                $vector[] = $gray;
            }
        }

        \imagedestroy($scaled);
        \imagedestroy($source);

        return $vector;
    }

    private function resolveFaceIdApiUrl(): string
    {
        $configured = $_ENV['FACE_ID_API_URL'] ?? $_SERVER['FACE_ID_API_URL'] ?? getenv('FACE_ID_API_URL') ?: '';
        $baseUrl = trim((string) $configured);

        return $baseUrl !== '' ? rtrim($baseUrl, '/') : 'http://127.0.0.1:8001';
    }

    private function extractFaceEmbedding(HttpClientInterface $httpClient, string $apiBaseUrl, string $imageDataUrl): ?array
    {
        try {
            $response = $httpClient->request('POST', $apiBaseUrl . '/extract', [
                'json' => ['image_data' => $imageDataUrl],
                'headers' => ['Accept' => 'application/json'],
                'timeout' => 10.0,
            ]);
            $status = $response->getStatusCode();
            if ($status === 400) {
                return null;
            }

            if ($status >= 400) {
                throw new \RuntimeException('Face extract request failed.');
            }

            $data = $response->toArray(false);
            $embedding = $data['embedding'] ?? null;
            if (!is_array($embedding) || $embedding === []) {
                return null;
            }

            return $embedding;
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Face extract request failed.', 0, $exception);
        }
    }

    private function computeEmbeddingSimilarity(array $probeEmbedding, array $storedEmbedding): ?float
    {
        if ($probeEmbedding === [] || $storedEmbedding === [] || count($probeEmbedding) !== count($storedEmbedding)) {
            return null;
        }

        $dotProduct = 0.0;
        $probeMagnitude = 0.0;
        $storedMagnitude = 0.0;

        $count = count($probeEmbedding);
        for ($index = 0; $index < $count; $index++) {
            $probeValue = (float) $probeEmbedding[$index];
            $storedValue = (float) $storedEmbedding[$index];

            $dotProduct += $probeValue * $storedValue;
            $probeMagnitude += $probeValue * $probeValue;
            $storedMagnitude += $storedValue * $storedValue;
        }

        $denominator = sqrt($probeMagnitude) * sqrt($storedMagnitude);
        if ($denominator <= 1.0e-8) {
            return null;
        }

        $similarity = $dotProduct / $denominator;

        return max(0.0, min(1.0, $similarity));
    }

    private function binaryImageToDataUrl(string $binaryImage): ?string
    {
        if ($binaryImage === '') {
            return null;
        }

        $mime = 'image/jpeg';
        if (str_starts_with($binaryImage, "\x89PNG\r\n\x1a\n")) {
            $mime = 'image/png';
        } elseif (str_starts_with($binaryImage, "GIF87a") || str_starts_with($binaryImage, "GIF89a")) {
            $mime = 'image/gif';
        } elseif (str_starts_with($binaryImage, "\xFF\xD8")) {
            $mime = 'image/jpeg';
        }

        return 'data:' . $mime . ';base64,' . base64_encode($binaryImage);
    }

    private function normalizeStoredFaceBinary(mixed $storedFace): ?string
    {
        if (is_resource($storedFace)) {
            $storedFace = stream_get_contents($storedFace);
        }

        if (!is_string($storedFace) || $storedFace === '') {
            return null;
        }

        if ($this->isSupportedImageBinary($storedFace)) {
            return $storedFace;
        }

        $trimmed = trim($storedFace);
        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, 'data:image/')) {
            return $this->decodeDataUrlImage($trimmed);
        }

        $decoded = base64_decode($trimmed, true);
        if (!is_string($decoded) || $decoded === '') {
            return null;
        }

        return $this->isSupportedImageBinary($decoded) ? $decoded : null;
    }

    private function isSupportedImageBinary(string $binaryImage): bool
    {
        return str_starts_with($binaryImage, "\xFF\xD8")
            || str_starts_with($binaryImage, "\x89PNG\r\n\x1a\n")
            || str_starts_with($binaryImage, 'GIF87a')
            || str_starts_with($binaryImage, 'GIF89a')
            || (str_starts_with($binaryImage, 'RIFF') && str_contains(substr($binaryImage, 0, 16), 'WEBP'));
    }

    private function appendFaceIdAuditLog(string $event, array $context = []): void
    {
        try {
            $logFile = dirname(__DIR__, 2) . '/var/log/faceid_login.log';
            $line = [
                'time' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'event' => $event,
                'context' => $context,
            ];

            @file_put_contents($logFile, json_encode($line, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
        } catch (\Throwable) {
            // Best-effort only; logging failure must not break authentication.
        }
    }

    #[Route(path: '/two-factor', name: 'app_two_factor_verify', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function twoFactor(Request $request, Connection $connection): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            return $this->redirectToRoute('app_login');
        }

        if ($request->getSession()->get('two_factor_verified', false) === true) {
            return $this->redirectToRoute('app_mainpage');
        }

        $expiresAtRaw = $connection->fetchOne(
            'SELECT two_factor_expiry FROM `user` WHERE id = ?',
            [(int) $user->getId()],
            [ParameterType::INTEGER]
        );

        $expiresIn = null;
        if (is_string($expiresAtRaw) && $expiresAtRaw !== '') {
            try {
                $expiresAt = new \DateTimeImmutable($expiresAtRaw);
                $expiresIn = max(0, $expiresAt->getTimestamp() - time());
            } catch (\Throwable $e) {
                $expiresIn = null;
            }
        }

        return $this->render('security/two_factor.html.twig', [
            'expires_in' => $expiresIn,
            'user_email' => (string) $user->getEmail(),
        ]);
    }

    #[Route(path: '/two-factor/verify', name: 'app_two_factor_verify_submit', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function verifyTwoFactor(Request $request, Connection $connection): Response
    {
        if (!$this->isCsrfTokenValid('two_factor_verify', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid verification request.');
            return $this->redirectToRoute('app_two_factor_verify');
        }

        $code = preg_replace('/\D+/', '', (string) $request->request->get('code', ''));
        if (!is_string($code) || strlen($code) !== 6) {
            $this->addFlash('error', 'Please enter a valid 6-digit code.');
            return $this->redirectToRoute('app_two_factor_verify');
        }

        $user = $this->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            return $this->redirectToRoute('app_login');
        }

        $row = $connection->executeQuery(
            'SELECT two_factor_code, two_factor_expiry FROM `user` WHERE id = ? LIMIT 1',
            [(int) $user->getId()],
            [ParameterType::INTEGER]
        )->fetchAssociative();

        if (!$row || empty($row['two_factor_code']) || empty($row['two_factor_expiry'])) {
            $this->addFlash('error', 'No verification code found. Please request a new code.');
            return $this->redirectToRoute('app_two_factor_verify');
        }

        $isValidCode = hash_equals((string) $row['two_factor_code'], $code);
        $isNotExpired = false;

        try {
            $expiry = new \DateTimeImmutable((string) $row['two_factor_expiry']);
            $isNotExpired = $expiry >= new \DateTimeImmutable('now');
        } catch (\Throwable $e) {
            $isNotExpired = false;
        }

        if (!$isValidCode || !$isNotExpired) {
            $this->addFlash('error', 'Invalid or expired code.');
            return $this->redirectToRoute('app_two_factor_verify');
        }

        $connection->executeStatement(
            'UPDATE `user` SET two_factor_code = NULL, two_factor_expiry = NULL WHERE id = ?',
            [(int) $user->getId()],
            [ParameterType::INTEGER]
        );

        $request->getSession()->set('two_factor_verified', true);
        $request->getSession()->remove('two_factor_pending_user_id');
        $this->addFlash('success', '2FA verified successfully.');

        return $this->redirectToRoute('app_mainpage');
    }

    #[Route(path: '/two-factor/resend', name: 'app_two_factor_resend', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function resendTwoFactorCode(Request $request, Connection $connection, MailerInterface $mailer): Response
    {
        if (!$this->isCsrfTokenValid('two_factor_resend', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid resend request.');
            return $this->redirectToRoute('app_two_factor_verify');
        }

        $user = $this->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            return $this->redirectToRoute('app_login');
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiry = new \DateTimeImmutable('+10 minutes');

        $connection->executeStatement(
            'UPDATE `user` SET two_factor_code = ?, two_factor_expiry = ? WHERE id = ?',
            [$code, $expiry->format('Y-m-d H:i:s'), (int) $user->getId()],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER]
        );

        try {
            $email = (new Email())
                ->from((string) ($_ENV['EMAIL_FROM'] ?? $_SERVER['EMAIL_FROM'] ?? 'noreply@rehletna.tn'))
                ->to((string) $user->getEmail())
                ->subject('Your new Rehletna 2FA verification code')
                ->text(
                    "Hello " . (string) ($user->getFullName() ?: $user->getUsername()) . ",\n\n"
                    . "Your new verification code is: {$code}\n"
                    . "This code expires in 10 minutes."
                );

            $mailer->send($email);
            $this->addFlash('success', 'A new verification code has been sent to your email.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Code regenerated, but email could not be sent.');
        }

        return $this->redirectToRoute('app_two_factor_verify');
    }
}