<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\FaceProfile;
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
use Symfony\Contracts\Cache\CacheInterface;

#[Route('/api', name: 'api_')]
class FaceApiController extends BaseController
{
    private const AUTH_COOKIE_NAME = 'AUTH_TOKEN';
    private const COOKIE_LIFETIME = 3600;
    private const FACE_LOGIN_RATE_LIMIT = 10;
    private const FACE_LOGIN_RATE_WINDOW = 60;
    private const DEFAULT_MATCH_THRESHOLD = 0.6;

    public function __construct(
        private FaceRecognitionClient $faceRecognitionClient,
        private FaceProfileRepository $faceProfileRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private JWTTokenManagerInterface $jwtManager,
        private CacheInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * POST /api/face/enroll — enroll face for the authenticated user (base64 image).
     */
    #[Route('/face/enroll', name: 'face_enroll', methods: ['POST'])]
    public function enroll(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json([
                'error' => 'unauthorized',
                'message' => 'Authentication required.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $imageBase64 = $this->extractBase64((string) ($payload['image'] ?? ''));

        if ($imageBase64 === '') {
            return $this->json([
                'error' => 'invalid_request',
                'message' => 'Image is required (base64 or data URL).',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->faceRecognitionClient->isAvailable()) {
            return $this->json([
                'error' => 'service_unavailable',
                'message' => 'Face recognition service is not running. Start it from the project root: python start_ai_services.py (face on port 5000) or: python -m uvicorn ai_face_service.main:app --host 127.0.0.1 --port 5000',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        try {
            $embedResult = $this->faceRecognitionClient->embed($imageBase64);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'error' => 'invalid_image',
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            $this->logger->error('Face enroll: service error', [
                'user_id' => $user->getId(),
                'message' => $e->getMessage(),
            ]);
            return $this->json([
                'error' => 'service_error',
                'message' => 'Face enrollment temporarily unavailable. Please try again.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $embedding = $embedResult['embedding'] ?? [];
        if (empty($embedding)) {
            return $this->json([
                'error' => 'invalid_image',
                'message' => 'No face embedding obtained.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $faceProfile = $this->faceProfileRepository->findOneByUser($user->getId());
        if ($faceProfile === null) {
            $faceProfile = new FaceProfile();
            $faceProfile->setUser($user);
            $this->entityManager->persist($faceProfile);
        }
        $faceProfile->setEmbedding($embedding);

        $user->recordFaceEnrolledAt(new \DateTime());
        $user->resetFaceFailedAttempts();
        $this->entityManager->flush();

        $this->logger->info('Face enrolled', ['user_id' => $user->getId()]);

        return $this->json([
            'message' => 'Face enrolled successfully.',
            'enrolled_at' => $user->getFaceEnrolledAt()?->format(\DateTimeInterface::ATOM),
        ], Response::HTTP_OK);
    }

    /**
     * POST /api/face-login — public face login (base64 image). Rate limited by IP.
     */
    #[Route('/face-login', name: 'face_login', methods: ['POST'])]
    public function faceLogin(Request $request): JsonResponse
    {
        $ip = $request->getClientIp() ?? 'unknown';
        $cacheKey = 'face_login_rate_' . md5($ip);

        $item = $this->cache->getItem($cacheKey);
        $count = $item->isHit() ? (int) $item->get() : 0;
        if ($count >= self::FACE_LOGIN_RATE_LIMIT) {
            $this->logger->warning('Face login rate limit exceeded', ['ip' => $ip]);
            return $this->jsonWithClearedAuthCookie($request, [
                'error' => 'rate_limit',
                'message' => 'Too many attempts. Please try again in a minute.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }
        $item->set((string) ($count + 1));
        $item->expiresAfter(self::FACE_LOGIN_RATE_WINDOW);
        $this->cache->save($item);

        $payload = json_decode($request->getContent(), true) ?? [];
        $imageBase64 = $this->extractBase64((string) ($payload['image'] ?? ''));

        if ($imageBase64 === '') {
            return $this->jsonWithClearedAuthCookie($request, [
                'error' => 'invalid_request',
                'message' => 'Image is required (base64 or data URL).',
            ], Response::HTTP_BAD_REQUEST);
        }

        $candidates = $this->faceProfileRepository->findAllForMatch();
        if (empty($candidates)) {
            $this->logger->info('Face login: no enrolled face profiles', ['ip' => $ip]);
            return $this->jsonWithClearedAuthCookie($request, [
                'error' => 'face_not_enrolled',
                'message' => 'Face not enrolled.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $matchCandidates = array_map(fn (array $row) => [
            'user_id' => $row['user_id'],
            'embedding' => $row['embedding'],
        ], $candidates);

        try {
            $result = $this->faceRecognitionClient->match(
                $imageBase64,
                $matchCandidates,
                self::DEFAULT_MATCH_THRESHOLD
            );
        } catch (\InvalidArgumentException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Multiple faces')) {
                $this->logger->info('Face login: multiple faces detected', ['ip' => $ip]);
                return $this->jsonWithClearedAuthCookie($request, [
                    'error' => 'multiple_faces_detected',
                    'message' => 'Multiple faces detected.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            return $this->jsonWithClearedAuthCookie($request, [
                'error' => 'invalid_image',
                'message' => $msg,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            $this->logger->error('Face login: service error', [
                'ip' => $ip,
                'message' => $e->getMessage(),
            ]);
            return $this->jsonWithClearedAuthCookie($request, [
                'error' => 'service_error',
                'message' => 'Face recognition temporarily unavailable. Please try again.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if (!$result['matched'] || $result['user_id'] === null) {
            $this->logger->info('Face login: no match', [
                'ip' => $ip,
                'best_distance' => $result['distance'],
                'threshold' => $result['threshold'],
            ]);
            return $this->jsonWithClearedAuthCookie($request, [
                'error' => 'low_confidence',
                'message' => 'Low confidence.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $matchedUser = $this->userRepository->find($result['user_id']);
        if (!$matchedUser instanceof User) {
            return $this->jsonWithClearedAuthCookie($request, [
                'error' => 'face_not_enrolled',
                'message' => 'Face not recognized.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Enforce eligibility BEFORE JWT issuance (never authenticate banned/inactive)
        if ($matchedUser->isFaceLocked() || $matchedUser->isBanned() || !$matchedUser->isEmailVerified() || $matchedUser->getStatus() !== UserStatus::ACTIVE) {
            $this->debugFaceDecision('FAIL_BANNED_OR_INACTIVE', [
                'user_id' => $matchedUser->getId(),
                'email' => $matchedUser->getEmail(),
                'ip' => $ip,
            ]);
            return $this->jsonWithClearedAuthCookie($request, [
                'error' => 'account_disabled',
                'message' => 'Account is not eligible for face login.',
            ], Response::HTTP_FORBIDDEN);
        }

        $matchedUser->resetFaceFailedAttempts();
        $matchedUser->recordFaceLastVerified(new \DateTime());
        $matchedUser->recordLastLogin(new \DateTime());
        $matchedUser->setLastIp($ip);
        $this->faceProfileRepository->touchLastMatchedAtByUserId((int) $matchedUser->getId());
        $this->entityManager->flush();

        try {
            $token = $this->jwtManager->create($matchedUser);
        } catch (\Throwable $e) {
            $this->logger->error('Face login: JWT create failed', [
                'user_id' => $matchedUser->getId(),
                'message' => $e->getMessage(),
            ]);
            return $this->jsonWithClearedAuthCookie($request, [
                'error' => 'login_error',
                'message' => 'Login failed. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $role = $matchedUser->getRole();
        $response = $this->json([
            'token' => $token,
            'user' => [
                'id' => $matchedUser->getId(),
                'email' => $matchedUser->getEmail(),
                'role' => $role?->value ?? 'CLIENT',
            ],
        ], Response::HTTP_OK);
        $response->headers->setCookie(Cookie::create(self::AUTH_COOKIE_NAME)
            ->withValue($token)
            ->withExpires(time() + self::COOKIE_LIFETIME)
            ->withPath('/')
            ->withSecure($request->isSecure())
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX));

        $this->logger->info('Face login success', ['user_id' => $matchedUser->getId(), 'ip' => $ip]);
        $this->debugFaceDecision('SUCCESS', [
            'user_id' => $matchedUser->getId(),
            'email' => $matchedUser->getEmail(),
            'ip' => $ip,
        ]);

        return $response;
    }

    /**
     * Return JSON response and expire any existing AUTH_TOKEN cookie.
     * Prevents identity confusion if a stale cookie is present on a failing face-login attempt.
     *
     * @param array<string, mixed> $data
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
        $this->logger->info('Face login decision', array_merge(['decision' => $decision], $context));
    }

    private function extractBase64(string $input): string
    {
        if (preg_match('#^data:image/(jpeg|jpg|png);base64,(.+)$#i', $input, $m)) {
            return trim($m[2]);
        }
        return trim($input) !== '' ? trim($input) : '';
    }
}
