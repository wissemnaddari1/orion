<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserBanRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserBanRepository::class)]
#[ORM\Table(name: 'user_ban')]
#[ORM\Index(columns: ['user_id'], name: 'idx_user_ban_user_id')]
#[ORM\Index(columns: ['is_active'], name: 'idx_user_ban_is_active')]
class UserBan
{
    public const TYPE_TEMP = 'TEMP';
    public const TYPE_PERM = 'PERM';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'banned_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $bannedBy = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $reason = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(name: 'banned_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $bannedAt;

    #[ORM\Column(name: 'ends_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column(name: 'lifted_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $liftedAt = null;

    #[ORM\Column(name: 'lift_reason', type: Types::TEXT, nullable: true)]
    private ?string $liftReason = null;

    #[ORM\Column(name: 'is_active', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(name: 'type', type: Types::STRING, length: 10)]
    private string $type = self::TYPE_TEMP;

    public function __construct()
    {
        $this->bannedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getBannedBy(): ?User
    {
        return $this->bannedBy;
    }

    public function setBannedBy(?User $bannedBy): static
    {
        $this->bannedBy = $bannedBy;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): static
    {
        $this->reason = $reason;
        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;
        return $this;
    }

    public function getBannedAt(): ?\DateTimeImmutable
    {
        return $this->bannedAt;
    }

    /** @internal Doctrine / lifecycle only; use recordBannedAt() from app code. */
    protected function setBannedAt(\DateTimeImmutable $bannedAt): static
    {
        $this->bannedAt = $bannedAt;
        return $this;
    }

    public function recordBannedAt(?\DateTimeImmutable $when = null): static
    {
        $this->bannedAt = $when ?? new \DateTimeImmutable();
        return $this;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    /** @internal Doctrine / lifecycle only; use recordEndsAt() from app code. */
    protected function setEndsAt(?\DateTimeImmutable $endsAt): static
    {
        $this->endsAt = $endsAt;
        return $this;
    }

    public function recordEndsAt(?\DateTimeImmutable $when): static
    {
        $this->endsAt = $when;
        return $this;
    }

    public function getLiftedAt(): ?\DateTimeImmutable
    {
        return $this->liftedAt;
    }

    /** @internal Doctrine / lifecycle only; use recordLiftedAt() from app code. */
    protected function setLiftedAt(?\DateTimeImmutable $liftedAt): static
    {
        $this->liftedAt = $liftedAt;
        return $this;
    }

    public function recordLiftedAt(?\DateTimeImmutable $when = null): static
    {
        $this->liftedAt = $when ?? new \DateTimeImmutable();
        return $this;
    }

    public function getLiftReason(): ?string
    {
        return $this->liftReason;
    }

    public function setLiftReason(?string $liftReason): static
    {
        $this->liftReason = $liftReason;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }
}
