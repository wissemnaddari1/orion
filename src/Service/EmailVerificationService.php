<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Email Verification Service
 * Works directly with User entity (no separate verification table)
 */
class EmailVerificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private string $mailerFrom,
        private ?LoggerInterface $logger = null
    ) {}

    /**
     * Generate and send verification code to user
     */
    public function sendVerificationCode(User $user): bool
    {
        try {
            // Generate 6-digit code
            $code = sprintf('%06d', random_int(0, 999999));

            // Store in user entity
            $user->setEmailVerificationCode($code);
            $user->setVerificationExpiresAt(new \DateTime('+15 minutes'));

            $this->entityManager->flush();

            $this->logger?->info('Generated verification code for user', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
            ]);

            // Send email
            $email = (new TemplatedEmail())
                ->from(new Address($this->mailerFrom, 'Orion Platform'))
                ->to(new Address($user->getEmail(), $user->getFullName()))
                ->subject('Verify Your Email - Orion')
                ->htmlTemplate('emails/verify_email.html.twig')
                ->context([
                    'user' => $user,
                    'code' => $code,
                    'expiresAt' => $user->getEmailVerificationExpiresAt(),
                ]);

            $this->mailer->send($email);

            $this->logger?->info('Verification email sent successfully', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
            ]);

            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logger?->error('Failed to send verification email', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'error' => $e->getMessage(),
            ]);
            return false;
        } catch (\Exception $e) {
            $this->logger?->error('Unexpected error sending verification email', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Verify code and activate user account
     */
    public function verifyCode(User $user, string $code): bool
    {
        // Check if code matches
        if ($user->getEmailVerificationCode() !== $code) {
            $this->logger?->warning('Invalid verification code attempt', [
                'user_id' => $user->getId(),
            ]);
            return false;
        }

        // Check if code is expired
        if (!$user->isEmailVerificationCodeValid()) {
            $this->logger?->warning('Expired verification code attempt', [
                'user_id' => $user->getId(),
            ]);
            return false;
        }

        // Mark as verified and clear code
        $user->setEmailVerified(true);
        $user->setEmailVerificationCode(null);
        $user->setVerificationExpiresAt(null);

        $this->entityManager->flush();

        $this->logger?->info('Email verified successfully', [
            'user_id' => $user->getId(),
        ]);

        return true;
    }

    /**
     * Check if user has pending verification
     */
    public function hasPendingVerification(User $user): bool
    {
        return !$user->isEmailVerified() && $user->isEmailVerificationCodeValid();
    }
}
