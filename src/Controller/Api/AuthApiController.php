<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use App\Service\TwoFactorTempTokenService;
use App\Service\TwoFactorTotpService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
class AuthApiController extends BaseController
{
    private const AUTH_COOKIE_NAME = 'AUTH_TOKEN';
    private const COOKIE_LIFETIME = 3600; // 1 hour, match token_ttl

    private const TWO_FACTOR_MAX_ATTEMPTS = 5;
    private const TWO_FACTOR_LOCK_SECONDS = 30;

    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private TwoFactorTempTokenService $twoFactorTempTokenService,
        private TwoFactorTotpService $twoFactorTotpService,
    ) {
    }

    /**
     * POST /api/login â€” validate credentials, ensure ACTIVE + emailVerified, return JWT in JSON.
     * Stateless: token is returned in JSON only (frontend stores in localStorage and sends in headers).
     */
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $privateKeyPath = $this->getParameter('kernel.project_dir') . '/config/jwt/private.pem';
        if (!is_readable($privateKeyPath)) {
            $this->logger->error('JWT keys not found', ['path' => $privateKeyPath]);
            return $this->json([
                'error' => 'service_unavailable',
                'message' => 'Authentication service temporarily unavailable. Please try again later.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($email === '' || $password === '') {
            return $this->json([
                'error' => 'invalid_credentials',
                'message' => 'Email and password are required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            $this->logger->info('Login failed: user not found', ['email' => $email]);
            return $this->json([
                'error' => 'invalid_credentials',
                'message' => 'Invalid credentials.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Login lockout: too many failed password attempts
        if ($user->isLoginLocked()) {
            $until = $user->getLoginLockedUntil();
            $minutes = $until ? (int) ceil(($until->getTimestamp() - time()) / 60) : 10;
            return $this->json([
                'error' => 'account_locked',
                'message' => 'Too many failed attempts. Try again in ' . max(1, $minutes) . ' minutes.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        // Face lockout: too many failed face attempts
        if ($user->isFaceLocked()) {
            $this->logger->info('Login denied: face locked', ['user_id' => $user->getId()]);
            return $this->json([
                'error' => 'account_locked',
                'message' => 'Too many attempts. Try again later.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        // Banned by admin
        if ($user->isBanned()) {
            return $this->json([
                'error' => 'account_not_active',
                'message' => 'Your account has been restricted. Please contact support.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Validation: status and email_verified BEFORE password (fail fast, don't leak password validity)
        if (!$user->isEmailVerified()) {
            return $this->json([
                'error' => 'email_not_verified',
                'message' => 'Please verify your email before logging in.',
            ], Response::HTTP_FORBIDDEN);
        }

        $status = $user->getStatus();
        if ($status !== UserStatus::ACTIVE) {
            $message = match ($status) {
                UserStatus::PENDING => 'Your account is not active yet. Please verify your email.',
                UserStatus::SUSPENDED => 'Your account has been suspended.',
                UserStatus::BANNED => 'Your account has been banned.',
                default => 'Your account is not active.',
            };
            return $this->json([
                'error' => 'account_not_active',
                'message' => $message,
            ], Response::HTTP_FORBIDDEN);
        }

        // Password check via Symfony UserPasswordHasherInterface
        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            $user->incrementFailedLoginAttempts(3, 10);
            $this->entityManager->flush();
            $this->logger->info('Login failed: invalid password', ['user_id' => $user->getId()]);
            return $this->json([
                'error' => 'invalid_credentials',
                'message' => 'Invalid credentials.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Success: reset login and face failed attempts
        $user->resetLoginAttempts();
        $user->resetFaceFailedAttempts();
        $this->entityManager->flush();

        // If 2FA is enabled, do NOT issue JWT; return temp token for /api/auth/2fa/verify
        if ($user->isTwoFactorEnabled()) {
            $tempToken = $this->twoFactorTempTokenService->create($user);

            return $this->json([
                'two_factor_required' => true,
                'temp_token' => $tempToken,
                'message' => 'Enter the 6-digit code from your authenticator app.',
            ], Response::HTTP_OK);
        }

        try {
            $token = $this->jwtManager->create($user);
        } catch (\Throwable $e) {
            $this->logger->error('Login failed: JWT create error', [
                'user_id' => $user->getId(),
                'exception' => $e,
            ]);
            return $this->json([
                'error' => 'login_error',
                'message' => 'Authentication service temporarily unavailable. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $user->recordLastLogin(new \DateTime());
        $user->setLastIp($request->getClientIp() ?? '');
        $this->entityManager->flush();

        $role = $user->getRole();
        $payload = [
            'token' => $token,
            'user' => [
                'email' => $user->getEmail(),
                'role' => $role?->value ?? 'CLIENT',
            ],
        ];

        $response = $this->json($payload, Response::HTTP_OK);
        $response->headers->setCookie($this->createAuthCookie($request, $token));
        return $response;
    }

    /**
     * POST /api/auth/2fa/verify â€” Accept temp_token + otp_code (or backup code). If valid, issue full JWT.
     * Rate limited: 5 failed attempts lock for 30 seconds.
     */
    #[Route('/auth/2fa/verify', name: 'auth_2fa_verify', methods: ['POST'])]
    public function twoFactorVerify(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];
        $tempToken = trim((string) ($data['temp_token'] ?? $data['tempToken'] ?? ''));
        $otpCode = trim((string) ($data['otp_code'] ?? $data['otpCode'] ?? $data['code'] ?? ''));

        if ($tempToken === '' || $otpCode === '') {
            return $this->json([
                'error' => 'invalid_request',
                'message' => 'temp_token and otp_code are required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->twoFactorTempTokenService->consume($tempToken);
        if (!$user instanceof User) {
            return $this->json([
                'error' => 'invalid_temp_token',
                'message' => 'Invalid or expired temporary token. Please log in again.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isTwoFactorLocked()) {
            return $this->json([
                'error' => 'too_many_attempts',
                'message' => 'Too many failed attempts. Try again in 30 seconds.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $secret = $user->getTwoFactorSecret();
        $valid = false;
        if ($secret !== null && $secret !== '') {
            $valid = $this->twoFactorTotpService->verify($secret, $otpCode);
        }
        if (!$valid) {
            $newCodes = $this->twoFactorTotpService->verifyAndConsumeBackupCode($user, $otpCode);
            if ($newCodes !== null) {
                $user->setTwoFactorBackupCodes($newCodes);
                $this->entityManager->flush();
                $valid = true;
            }
        }

        if (!$valid) {
            $user->incrementTwoFactorFailedAttempts(self::TWO_FACTOR_MAX_ATTEMPTS, self::TWO_FACTOR_LOCK_SECONDS);
            $this->entityManager->flush();
            $this->logger->info('2FA verify failed', ['user_id' => $user->getId()]);

            return $this->json([
                'error' => 'invalid_code',
                'message' => 'Invalid code. Please try again.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user->resetTwoFactorFailedAttempts();
        $user->recordLastLogin(new \DateTime());
        $user->setLastIp($request->getClientIp() ?? '');
        $this->entityManager->flush();

        try {
            $token = $this->jwtManager->create($user);
        } catch (\Throwable $e) {
            $this->logger->error('2FA verify: JWT create failed', ['user_id' => $user->getId(), 'exception' => $e]);
            return $this->json([
                'error' => 'login_error',
                'message' => 'Authentication service temporarily unavailable. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $role = $user->getRole();
        $payload = [
            'token' => $token,
            'user' => [
                'email' => $user->getEmail(),
                'role' => $role?->value ?? 'CLIENT',
            ],
        ];

        $response = $this->json($payload, Response::HTTP_OK);
        $response->headers->setCookie($this->createAuthCookie($request, $token));
        return $response;
    }

    /**
     * POST /api/refresh â€” issue a new JWT for the currently authenticated user (via Bearer or cookie).
     */
    #[Route('/refresh', name: 'refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json([
                'error' => 'unauthorized',
                'message' => 'Authentication required.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $token = $this->jwtManager->create($user);
        } catch (\Throwable $e) {
            $this->logger->error('JWT refresh failed', ['user_id' => $user->getId(), 'exception' => $e]);
            return $this->json([
                'error' => 'service_unavailable',
                'message' => 'Authentication service temporarily unavailable. Please try again later.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
        $role = $user->getRole();
        $payload = [
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'role' => $role?->value ?? 'CLIENT',
            ],
        ];

        $response = new JsonResponse($payload, Response::HTTP_OK);
        $response->headers->setCookie($this->createAuthCookie($request, $token));
        return $response;
    }

    /**
     * POST /api/logout â€” clear JWT cookie and signal frontend to clear localStorage.
     */
    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $response = new JsonResponse([
            'message' => 'Logged out.',
            'clearToken' => true // Signal frontend to clear localStorage
        ], Response::HTTP_OK);
        $response->headers->setCookie(Cookie::create(self::AUTH_COOKIE_NAME)
            ->withValue('')
            ->withExpires(1)
            ->withPath('/')
            ->withSecure($request->isSecure())
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX));

        return $response;
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
