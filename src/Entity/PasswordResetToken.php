<?php

namespace App\Entity;

use App\Repository\PasswordResetTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Ignore;

#[ORM\Entity(repositoryClass: PasswordResetTokenRepository::class)]
#[ORM\Table(name: 'password_reset_tokens')]
class PasswordResetToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'token_hash', type: Types::STRING, length: 64)]
    #[Ignore]
    private string $tokenHash;

    #[ORM\Column(name: 'requested_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $requestedAt;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $expiresAt;

    #[ORM\Column(name: 'used_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $usedAt = null;

    public function __construct(User $user, #[\SensitiveParameter] string $tokenHash, \DateTimeInterface $expiresAt)
    {
        $this->user = $user;
        $this->tokenHash = $tokenHash;
        $this->requestedAt = new \DateTime();
        $this->expiresAt = $expiresAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getRequestedAt(): \DateTimeInterface
    {
        return $this->requestedAt;
    }

    public function getExpiresAt(): \DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function getUsedAt(): ?\DateTimeInterface
    {
        return $this->usedAt;
    }

    /** @internal Doctrine / lifecycle only; use markUsed() from app code. */
    protected function setUsedAt(?\DateTimeInterface $usedAt): self
    {
        $this->usedAt = $usedAt;
        return $this;
    }

    public function markUsed(?\DateTimeInterface $when = null): self
    {
        $this->usedAt = $when ?? new \DateTime();
        return $this;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTime();
    }

    public function isUsed(): bool
    {
        return $this->usedAt !== null;
    }
}
