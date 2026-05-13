<?php

namespace App\Service;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Repository\PasswordResetTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PasswordResetService
{
    public const TOKEN_TTL_MINUTES = 30;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private PasswordResetTokenRepository $tokenRepository,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private string $mailerFrom,
        private ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Create a reset request for the user. Returns the raw token (to be sent in email only; never store raw).
     */
    public function createRequest(User $user): string
    {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = new \DateTime('+' . self::TOKEN_TTL_MINUTES . ' minutes');

        $token = new PasswordResetToken($user, $tokenHash, $expiresAt);
        $this->entityManager->persist($token);
        $this->entityManager->flush();

        $this->logger?->info('Password reset token created', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
        ]);

        return $rawToken;
    }

    /**
     * Send the reset email with link containing the raw token.
     */
    public function sendResetEmail(User $user, string $rawToken): bool
    {
        try {
            $resetUrl = $this->urlGenerator->generate('app_reset_password', [
                'token' => $rawToken,
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            $email = (new TemplatedEmail())
                ->from(new Address($this->mailerFrom, 'Orion Platform'))
                ->to(new Address($user->getEmail(), $user->getFullName()))
                ->subject('Reset your password - Orion')
                ->htmlTemplate('emails/reset_password.html.twig')
                ->context([
                    'user' => $user,
                    'resetUrl' => $resetUrl,
                    'expiresAt' => new \DateTime('+' . self::TOKEN_TTL_MINUTES . ' minutes'),
                ]);

            $this->mailer->send($email);

            $this->logger?->info('Password reset email sent', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
            ]);

            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logger?->error('Failed to send password reset email', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Find a valid token by raw token; returns the token entity or null.
     */
    public function findValidToken(string $rawToken): ?PasswordResetToken
    {
        return $this->tokenRepository->findValidByToken($rawToken);
    }

    /**
     * Consume the token: mark as used and return the user. Caller must update password and flush.
     */
    public function consumeToken(PasswordResetToken $token): User
    {
        $token->markUsed(new \DateTime());
        $this->entityManager->flush();

        $this->tokenRepository->invalidateAllForUser($token->getUser());

        $this->logger?->info('Password reset token consumed', [
            'user_id' => $token->getUser()->getId(),
        ]);

        return $token->getUser();
    }
}
