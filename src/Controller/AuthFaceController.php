<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserStatus;
use App\Repository\FaceProfileRepository;
use App\Repository\UserRepository;
use App\Service\FaceRecognitionClient;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AuthFaceController extends BaseController
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCK_MINUTES = 10;
    private const DEFAULT_MATCH_THRESHOLD = 0.6;
    private const AUTH_COOKIE_NAME = 'AUTH_TOKEN';
    private const COOKIE_LIFETIME = 3600; // 1 hour

    public function __construct(
        private FaceRecognitionClient $faceRecognitionClient,
        private FaceProfileRepository $faceProfileRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator,
        private JWTTokenManagerInterface $jwtManager,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[Route('/auth/face/login', name: 'auth_face_login', methods: ['GET'])]
    public function faceLogin(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('client_dashboard');
        }

        return $this->render('security/face_login.html.twig');
    }

    #[Route('/auth/face/verify', name: 'auth_face_verify', methods: ['POST'])]
    public function verify(Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $this->jsonWithClearedAuthCookie($request, ['success' => false, 'error' => 'Invalid request payload.'], 400);
        }

        $imageBase64 = $this->normalizeBase64((string) ($payload['imageBase64'] ?? $payload['image'] ?? ''));

        if ($imageBase64 === '') {
            return $this->jsonWithClearedAuthCookie($request, ['success' => false, 'error' => 'Image is required.'], 400);
        }

        $candidates = $this->faceProfileRepository->findAllForMatch();
        if (empty($candidates)) {
            $this->logger?->info('Face verify: no enrolled face profiles');
            return $this->jsonWithClearedAuthCookie($request, ['success' => false, 'error' => 'Face not recognized.'], 401);
        }

        $matchPayload = [
            'image_base64' => $imageBase64,
            'candidates' => array_map(fn (array $row) => [
                'user_id' => $row['user_id'],
                'embedding' => $row['embedding'],
            ], $candidates),
            'threshold' => self::DEFAULT_MATCH_THRESHOLD,
        ];

        try {
            $result = $this->faceRecognitionClient->match(
                $imageBase64,
                $matchPayload['candidates'],
                self::DEFAULT_MATCH_THRESHOLD
            );
        } catch (\InvalidArgumentException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Multiple faces')) {
                return $this->jsonWithClearedAuthCookie($request, ['success' => false, 'error' => 'Multiple faces detected.'], 422);
            }
            return $this->jsonWithClearedAuthCookie($request, ['success' => false, 'error' => $msg], 422);
        } catch (\Throwable $e) {
            $this->logger?->error('Face verify: service error', ['message' => $e->getMessage()]);
            return $this->jsonWithClearedAuthCookie($request, ['success' => false, 'error' => 'Face verification failed.'], 500);
        }

        if (!$result['matched'] || $result['user_id'] === null) {
            $this->debugFaceDecision('FAIL_NO_MATCH', [
                'distance' => $result['distance'] ?? null,
                'threshold' => $result['threshold'] ?? self::DEFAULT_MATCH_THRESHOLD,
            ]);
            return $this->jsonWithClearedAuthCookie($request, ['success' => false, 'error' => 'Face not recognized.'], 401);
        }

        $user = $this->userRepository->find($result['user_id']);
        if (!$user instanceof User) {
            $this->debugFaceDecision('FAIL_NO_USER', ['matched_user_id' => $result['user_id']]);
            return $this->jsonWithClearedAuthCookie($request, ['success' => false, 'error' => 'Face not recognized.'], 401);
        }

        // Enforce account eligibility BEFORE issuing any JWT
        if ($user->isFaceLocked()) {
            $this->debugFaceDecision('FAIL_LOCKED', ['user_id' => $user->getId(), 'email' => $user->getEmail()]);
            return $this->jsonWithClearedAuthCookie($request, ['success' => false, 'error' => 'Account is not eligible for face login.'], 403);
        }
        if ($user->isBanned() || !$user->isEmailVerified() || $user->getStatus() !== UserStatus::ACTIVE) {
            $this->debugFaceDecision('FAIL_BANNED_OR_INACTIVE', ['user_id' => $user->getId(), 'email' => $user->getEmail()]);
            return $this->jsonWithClearedAuthCookie($request, ['success' => false, 'error' => 'Account is not eligible for face login.'], 403);
        }

        $user->resetFaceFailedAttempts();
        $user->recordFaceLastVerified(new \DateTime());
        $user->recordLastLogin(new \DateTime());
        $user->setLastIp($request->getClientIp());
        $this->faceProfileRepository->touchLastMatchedAtByUserId((int) $user->getId());
        $this->entityManager->flush();

        // Create JWT token and set cookie (stateless authentication)
        try {
            $token = $this->jwtManager->create($user);
        } catch (\Throwable $e) {
            $this->logger?->error('Face login: JWT create failed', [
                'user_id' => $user->getId(),
                'message' => $e->getMessage(),
            ]);
            return $this->jsonWithClearedAuthCookie($request, [
                'success' => false,
                'error' => 'Login failed. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $redirect = $this->getPostLoginRedirect($user);
        $this->debugFaceDecision('SUCCESS', ['user_id' => $user->getId(), 'email' => $user->getEmail()]);

        $response = $this->json([
            'success' => true,
            'redirect' => $redirect,
            'token' => $token, // Include token in response so frontend can store in localStorage
        ]);

        // Set JWT cookie so redirect to dashboard is authenticated
        $response->headers->setCookie($this->createAuthCookie($request, $token));

        return $response;
    }

    #[Route('/auth/face/enroll', name: 'auth_face_enroll', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function enrollPage(): Response
    {
        return $this->render('security/face_enroll.html.twig');
    }

    #[Route('/auth/face/enroll', name: 'auth_face_enroll_post', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function enrollPost(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['success' => false, 'error' => 'Unauthorized.'], 401);
        }

        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $this->json(['success' => false, 'error' => 'Invalid request payload.'], 400);
        }

        $imageBase64 = $this->normalizeBase64((string) ($payload['imageBase64'] ?? $payload['image'] ?? ''));

        if ($imageBase64 === '') {
            return $this->json(['success' => false, 'error' => 'Image is required.'], 400);
        }

        try {
            $embedResult = $this->faceRecognitionClient->embed($imageBase64);
        } catch (\InvalidArgumentException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Multiple faces')) {
                return $this->json(['success' => false, 'error' => 'Multiple faces detected.'], 422);
            }
            return $this->json(['success' => false, 'error' => $msg], 422);
        } catch (\Throwable $e) {
            $this->logger?->error('Face enroll: service error', ['message' => $e->getMessage()]);
            return $this->json(['success' => false, 'error' => 'Face enrollment failed.'], 500);
        }

        $embedding = $embedResult['embedding'] ?? [];
        if (empty($embedding)) {
            return $this->json(['success' => false, 'error' => 'No face embedding obtained.'], 422);
        }

        $faceProfile = $this->faceProfileRepository->findOneByUser($user->getId());
        if ($faceProfile === null) {
            $faceProfile = new \App\Entity\FaceProfile();
            $faceProfile->setUser($user);
            $this->entityManager->persist($faceProfile);
        }
        $faceProfile->setEmbedding($embedding);

        $user->recordFaceEnrolledAt(new \DateTime());
        $user->resetFaceFailedAttempts();
        $this->entityManager->flush();

        $this->logger?->info('Face enrolled', ['user_id' => $user->getId()]);

        return $this->json(['success' => true, 'message' => 'Face enrolled successfully.']);
    }

    private function normalizeBase64(string $input): string
    {
        $s = trim($input);
        if ($s === '') {
            return '';
        }
        if (str_starts_with($s, 'data:')) {
            $parts = explode(',', $s, 2);
            return trim($parts[1] ?? '');
        }
        return $s;
    }

    /**
     * Return JSON response and expire any existing AUTH_TOKEN cookie.
     * This prevents "session mixing" where a previously-authenticated JWT cookie
     * could persist across failed face login attempts.
     */
    private function jsonWithClearedAuthCookie(Request $request, array $data, int $status = 200): JsonResponse
    {
        $response = $this->json($data, $status);
        $response->headers->setCookie($this->expiredAuthCookie($request));

        return $response;
    }

    private function expiredAuthCookie(Request $request): Cookie
    {
        return Cookie::create(self::AUTH_COOKIE_NAME)
            ->withValue('')
            ->withExpires(1)
            ->withPath('/')
            ->withSecure($request->isSecure())
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }

    /**
     * Safe debug logging (no images/JWT). Enabled in dev or when FACE_LOGIN_DEBUG=1.
     *
     * @param array<string, mixed> $context
     */
    private function debugFaceDecision(string $decision, array $context = []): void
    {
        $enabled = (bool) $this->getParameter('kernel.debug') || (($_SERVER['FACE_LOGIN_DEBUG'] ?? null) === '1');
        if (!$enabled) {
            return;
        }
        $this->logger?->info('Face login decision', array_merge(['decision' => $decision], $context));
    }

    private function registerFailedAttempt(User $user): void
    {
        $attempts = (int) ($user->getFaceFailedAttempts() ?? 0);
        $attempts++;
        $user->setFaceFailedAttempts($attempts);

        if ($attempts >= self::MAX_FAILED_ATTEMPTS) {
            $user->recordFaceLockedUntil(new \DateTime('+' . self::LOCK_MINUTES . ' minutes'));
        }

        $this->entityManager->flush();
    }

    private function getPostLoginRedirect(User $user): string
    {
        $roles = $user->getRoles();
        if (in_array('ROLE_ADMIN', $roles, true)) {
            return $this->urlGenerator->generate('admin_dashboard');
        }
        if (in_array('ROLE_WORKER', $roles, true)) {
            return $this->urlGenerator->generate('worker_dashboard');
        }
        return $this->urlGenerator->generate('client_dashboard');
    }

    private function createAuthCookie(Request $request, string $token): Cookie
    {
        return Cookie::create(self::AUTH_COOKIE_NAME)
            ->withValue($token)
            ->withExpires(time() + self::COOKIE_LIFETIME)
            ->withPath('/')
            ->withSecure($request->isSecure())
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }
}
