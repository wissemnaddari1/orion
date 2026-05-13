<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Entity\User;
use App\Controller\BaseController;
use App\Service\TwoFactorTotpService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[Route('/settings', name: 'settings_')]
final class PrivacySecurityController extends BaseController
{
    private const TWO_FACTOR_MAX_ATTEMPTS = 5;
    private const TWO_FACTOR_LOCK_SECONDS = 30;

    public function __construct(
        private readonly TwoFactorTotpService $twoFactorTotpService,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * GET /settings/privacy-security — Privacy & Security page with 2FA card.
     */
    #[Route('/privacy-security', name: 'privacy_security', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $twoFactorEnrollment = null;
        if (!$user->isTwoFactorEnabled()) {
            $tempSecret = $user->getTwoFactorTempSecret();
            if ($tempSecret !== null && $tempSecret !== '') {
                $label = $user->getEmail();
                $provisioningUri = $this->twoFactorTotpService->getProvisioningUri($tempSecret, $label);
                $twoFactorEnrollment = [
                    'qr_data_uri' => $this->twoFactorTotpService->getQrCodeDataUri($provisioningUri),
                    'secret' => $tempSecret,
                ];
            }
        }

        return $this->render('pages/settings/privacy_security.html.twig', [
            'user' => $user,
            'topbar_title' => 'Privacy & Security',
            'notification_count' => 0,
            'two_factor_enrollment' => $twoFactorEnrollment,
        ]);
    }

    /**
     * POST /settings/2fa/start — Generate TOTP secret (do NOT enable yet). Store in session and redirect.
     */
    #[Route('/2fa/start', name: '2fa_start', methods: ['POST'])]
    public function twoFactorStart(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isTwoFactorEnabled()) {
            return $this->redirectToRoute('settings_privacy_security');
        }

        try {
            $secret = $this->twoFactorTotpService->generateSecret();
            $user->setTwoFactorTempSecret($secret);
            $this->entityManager->flush();

            return $this->redirectToRoute('settings_privacy_security');
        } catch (\Throwable $e) {
            $this->logger?->error('2FA start failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->redirectToRoute('settings_privacy_security');
        }
    }

    /**
     * POST /settings/2fa/confirm — Verify 6-digit code, then enable 2FA and generate backup codes.
     */
    #[Route('/2fa/confirm', name: '2fa_confirm', methods: ['POST'])]
    public function twoFactorConfirm(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isTwoFactorLocked()) {
            return $this->redirectToRoute('settings_privacy_security');
        }

        $tempSecret = $user->getTwoFactorTempSecret();
        if ($tempSecret === null || $tempSecret === '') {
            return $this->redirectToRoute('settings_privacy_security');
        }

        $otp = trim((string) $request->request->get('otp_code', ''));
        if ($otp === '') {
            return $this->redirectToRoute('settings_privacy_security');
        }

        if (!$this->twoFactorTotpService->verify($tempSecret, $otp)) {
            $user->incrementTwoFactorFailedAttempts(self::TWO_FACTOR_MAX_ATTEMPTS, self::TWO_FACTOR_LOCK_SECONDS);
            $this->entityManager->flush();
            return $this->redirectToRoute('settings_privacy_security');
        }

        $user->setTwoFactorSecret($tempSecret);
        $user->setTwoFactorTempSecret(null);
        $user->setTwoFactorEnabled(true);
        $user->resetTwoFactorFailedAttempts();

        [$plainBackupCodes, $hashedBackupCodes] = $this->twoFactorTotpService->generateBackupCodes();
        $user->setTwoFactorBackupCodes($hashedBackupCodes);
        $this->entityManager->flush();

        return $this->render('pages/settings/backup_codes.html.twig', [
            'user' => $user,
            'topbar_title' => 'Backup Codes',
            'notification_count' => 0,
            'backup_codes_count' => count($hashedBackupCodes),
            'backup_codes_hashed' => true,
            'plain_backup_codes' => $plainBackupCodes,
        ]);
    }

    /**
     * POST /settings/2fa/disable — Require password or current TOTP, then disable 2FA and clear secret + backup codes.
     */
    #[Route('/2fa/disable', name: '2fa_disable', methods: ['POST'])]
    public function twoFactorDisable(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->isTwoFactorEnabled()) {
            return $this->redirectToRoute('settings_privacy_security');
        }

        if ($user->isTwoFactorLocked()) {
            return $this->redirectToRoute('settings_privacy_security');
        }

        $password = $request->request->get('password', '');
        $otpCode = trim((string) $request->request->get('otp_code', ''));

        $valid = false;
        if ($password !== '') {
            $valid = $this->passwordHasher->isPasswordValid($user, $password);
        }
        if (!$valid && $otpCode !== '') {
            $secret = $user->getTwoFactorSecret();
            if ($secret !== null) {
                $valid = $this->twoFactorTotpService->verify($secret, $otpCode);
            }
        }
        if (!$valid && $otpCode !== '') {
            $newCodes = $this->twoFactorTotpService->verifyAndConsumeBackupCode($user, $otpCode);
            if ($newCodes !== null) {
                $valid = true;
            }
        }

        if (!$valid) {
            $user->incrementTwoFactorFailedAttempts(self::TWO_FACTOR_MAX_ATTEMPTS, self::TWO_FACTOR_LOCK_SECONDS);
            $this->entityManager->flush();
            return $this->redirectToRoute('settings_privacy_security');
        }

        $user->setTwoFactorEnabled(false);
        $user->setTwoFactorSecret(null);
        $user->setTwoFactorTempSecret(null);
        $user->setTwoFactorBackupCodes(null);
        $user->resetTwoFactorFailedAttempts();
        $this->entityManager->flush();

        return $this->redirectToRoute('settings_privacy_security');
    }

    /**
     * GET /settings/2fa/backup-codes — Show backup codes (one-time view; user must be authenticated).
     */
    #[Route('/2fa/backup-codes', name: '2fa_backup_codes', methods: ['GET'])]
    public function backupCodes(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->isTwoFactorEnabled()) {
            return $this->redirectToRoute('settings_privacy_security');
        }

        $codes = $user->getTwoFactorBackupCodes();
        $count = $codes !== null ? count($codes) : 0;

        return $this->render('pages/settings/backup_codes.html.twig', [
            'user' => $user,
            'topbar_title' => 'Backup Codes',
            'notification_count' => 0,
            'backup_codes_count' => $count,
            'backup_codes_hashed' => $count > 0,
        ]);
    }
}
