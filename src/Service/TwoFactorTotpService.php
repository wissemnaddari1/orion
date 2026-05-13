<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use OTPHP\TOTP;

/**
 * TOTP (Google Authenticator) secret generation, QR code, verification, and backup codes.
 * Uses constant-time comparison and ±1 step window for clock drift.
 */
final class TwoFactorTotpService
{
    private const TOTP_PERIOD = 30;
    private const TOTP_LEEWAY_SECONDS = 29; // Allow ±1 step (leeway must be < period)
    private const BACKUP_CODE_LENGTH = 8;
    private const BACKUP_CODE_COUNT = 10;

    public function __construct(
        private readonly string $appName = 'Orion',
    ) {
    }

    /**
     * Generate a new TOTP secret (base32). Store in user's twoFactorTempSecret until confirmed.
     */
    public function generateSecret(): string
    {
        $totp = TOTP::generate();

        return $totp->getSecret();
    }

    /**
     * Build provisioning URI for the authenticator app (used for QR code and manual entry).
     */
    public function getProvisioningUri(string $secret, string $label): string
    {
        $totp = TOTP::createFromSecret($secret);
        $totp->setPeriod(self::TOTP_PERIOD);
        $totp->setIssuer($this->appName);
        $totp->setLabel($label);

        return $totp->getProvisioningUri();
    }

    /**
     * Generate QR code as data URI (inline SVG) for the given provisioning URI.
     * Uses SVG instead of PNG to avoid requiring GD extension.
     */
    public function getQrCodeDataUri(string $provisioningUri): string
    {
        $builder = new Builder();
        
        $result = $builder->build(
            writer: new SvgWriter(),
            data: $provisioningUri,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Low,
            size: 220,
            margin: 10
        );

        return $result->getDataUri();
    }

    /**
     * Verify OTP code against secret. Uses ±1 step window. Constant-time comparison via OTPHP.
     */
    public function verify(string $secret, string $otp): bool
    {
        $otp = preg_replace('/\s+/', '', $otp);
        if ($otp === '' || strlen($otp) !== 6 || !ctype_digit($otp)) {
            return false;
        }

        $totp = TOTP::createFromSecret($secret);
        $totp->setPeriod(self::TOTP_PERIOD);

        return $totp->verify($otp, null, self::TOTP_LEEWAY_SECONDS);
    }

    /**
     * Verify backup code: constant-time compare. Returns updated list of hashed codes (without used one) or null.
     *
     * @return array<string>|null New backup codes array if code was valid, null otherwise
     */
    public function verifyAndConsumeBackupCode(User $user, string $code): ?array
    {
        $code = strtoupper(preg_replace('/\s+/', '', $code));
        $codes = $user->getTwoFactorBackupCodes();
        if ($codes === null || $codes === []) {
            return null;
        }

        $codes = array_values($codes);
        $userHash = $this->hashBackupCode($code);

        foreach ($codes as $index => $storedHash) {
            if ($this->hashEquals($storedHash, $userHash)) {
                unset($codes[$index]);

                return array_values($codes);
            }
        }

        return null;
    }

    /**
     * Generate backup codes (plain once, then store only hashes).
     *
     * @return array{0: array<string>, 1: array<string>} [plain codes, hashed codes]
     */
    public function generateBackupCodes(): array
    {
        $plain = [];
        $hashed = [];

        for ($i = 0; $i < self::BACKUP_CODE_COUNT; $i++) {
            $code = $this->randomBackupCode();
            $plain[] = $code;
            $hashed[] = $this->hashBackupCode($code);
        }

        return [$plain, $hashed];
    }

    private function randomBackupCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // No 0,O,1,I
        $code = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < self::BACKUP_CODE_LENGTH; $i++) {
            $code .= $chars[random_int(0, $max)];
        }

        return $code;
    }

    private function hashBackupCode(string $code): string
    {
        return hash('sha256', $code);
    }

    private function hashEquals(string $known, string $user): bool
    {
        return hash_equals($known, $user);
    }
}
