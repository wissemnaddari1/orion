<?php

namespace App\Entity;

use App\Entity\Traits\BlameableTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\ContractRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContractRepository::class)]
#[ORM\Table(name: 'contract')]
#[ORM\HasLifecycleCallbacks]
class Contract
{
    use BlameableTrait;
    use TimestampableTrait;

    // â”€â”€ Status constants â”€â”€
    // ── Status constants ──
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_PENDING_SIGN = 'PENDING_SIGN';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_DISPUTED = 'DISPUTED';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING_SIGN,
        self::STATUS_ACTIVE,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
        self::STATUS_DISPUTED,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    // â”€â”€ Relations â”€â”€
    // ── Relations ──
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'client_id', nullable: false)]
    private ?User $client = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'worker_id', nullable: false)]
    private ?User $worker = null;

    // â”€â”€ Core fields â”€â”€
    // ── Core fields ──
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $scope = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $agreedPrice = '0.00';

    #[ORM\Column(type: Types::STRING, length: 3, options: ['default' => 'USD'])]
    private string $currency = 'USD';

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $startDate;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $endDate;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'DRAFT'])]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, options: ['default' => '30.00'])]
    private string $upfrontPercent = '30.00';

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $upfrontPaid = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $upfrontPaidAt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $releasedAmount = '0.00';

    // â”€â”€ eSign fields â”€â”€
    // ── eSign fields ──
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $clientSigned = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $clientSignedAt = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $clientSignatureIp = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $workerSigned = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $workerSignedAt = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $workerSignatureIp = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $clientSignatureData = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $workerSignatureData = null;

    // â”€â”€ PDF / Files â”€â”€
    // ── PDF / Files ──
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $signedPdfPath = null;

    // â”€â”€ AI Risk Assessment â”€â”€

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $riskScore = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
    private ?string $riskLevel = null;

    // â”€â”€ Completion â”€â”€
    // ── Completion ──
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $cancellationReason = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $cancelledAt = null;

    // â”€â”€ Timestamps â”€â”€
    // ── Timestamps ──
    // â”€â”€ Milestones â”€â”€
    // ── Milestones ──
    #[ORM\OneToMany(targetEntity: Milestone::class, mappedBy: 'contract', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['orderIndex' => 'ASC'])]
    private Collection $milestones;

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    // CONSTRUCTOR
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    // ══════════════════════════════════════════════
    // CONSTRUCTOR
    // ══════════════════════════════════════════════
    public function __construct()
    {
        $this->milestones = new ArrayCollection();
        $this->initializeCreatedAt();
        $this->startDate = new \DateTime('today');
        $this->endDate = new \DateTime('today');
    }

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    // GETTERS & SETTERS
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    // ══════════════════════════════════════════════
    // GETTERS & SETTERS
    // ══════════════════════════════════════════════
    public function getId(): ?int { return $this->id; }

    public function getClient(): ?User { return $this->client; }
    public function setClient(?User $client): static { $this->client = $client; return $this; }

    public function getWorker(): ?User { return $this->worker; }
    public function setWorker(?User $worker): static { $this->worker = $worker; return $this; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getScope(): ?string { return $this->scope; }
    public function setScope(string $scope): static { $this->scope = $scope; return $this; }

    public function getAgreedPrice(): ?string { return $this->agreedPrice; }
    public function setAgreedPrice(string $agreedPrice): static { $this->agreedPrice = $agreedPrice; return $this; }

    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $currency): static { $this->currency = $currency; return $this; }

    public function getStartDate(): ?\DateTimeInterface { return $this->startDate; }
    public function setStartDate(\DateTimeInterface $startDate): static { $this->startDate = $startDate; return $this; }

    public function getEndDate(): ?\DateTimeInterface { return $this->endDate; }
    public function setEndDate(\DateTimeInterface $endDate): static { $this->endDate = $endDate; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getUpfrontPercent(): string { return $this->upfrontPercent; }
    public function setUpfrontPercent(string $upfrontPercent): static { $this->upfrontPercent = $upfrontPercent; return $this; }

    public function isUpfrontPaid(): bool { return $this->upfrontPaid; }
    public function setUpfrontPaid(bool $upfrontPaid): static { $this->upfrontPaid = $upfrontPaid; return $this; }

    public function getUpfrontPaidAt(): ?\DateTimeInterface { return $this->upfrontPaidAt; }
    public function setUpfrontPaidAt(?\DateTimeInterface $upfrontPaidAt): static { $this->upfrontPaidAt = $upfrontPaidAt; return $this; }

    public function getReleasedAmount(): string { return $this->releasedAmount; }
    public function setReleasedAmount(string $releasedAmount): static { $this->releasedAmount = $releasedAmount; return $this; }

    public function isClientSigned(): bool { return $this->clientSigned; }
    public function setClientSigned(bool $v): static { $this->clientSigned = $v; return $this; }

    public function getClientSignedAt(): ?\DateTimeInterface { return $this->clientSignedAt; }
    public function setClientSignedAt(?\DateTimeInterface $dt): static { $this->clientSignedAt = $dt; return $this; }

    public function getClientSignatureIp(): ?string { return $this->clientSignatureIp; }
    public function setClientSignatureIp(?string $v): static { $this->clientSignatureIp = $v; return $this; }

    public function isWorkerSigned(): bool { return $this->workerSigned; }
    public function setWorkerSigned(bool $v): static { $this->workerSigned = $v; return $this; }

    public function getWorkerSignedAt(): ?\DateTimeInterface { return $this->workerSignedAt; }
    public function setWorkerSignedAt(?\DateTimeInterface $dt): static { $this->workerSignedAt = $dt; return $this; }

    public function getWorkerSignatureIp(): ?string { return $this->workerSignatureIp; }
    public function setWorkerSignatureIp(?string $v): static { $this->workerSignatureIp = $v; return $this; }

    public function getClientSignatureData(): ?string { return $this->clientSignatureData; }
    public function setClientSignatureData(?string $v): static { $this->clientSignatureData = $v; return $this; }

    public function getWorkerSignatureData(): ?string { return $this->workerSignatureData; }
    public function setWorkerSignatureData(?string $v): static { $this->workerSignatureData = $v; return $this; }

    public function getSignedPdfPath(): ?string { return $this->signedPdfPath; }
    public function setSignedPdfPath(?string $v): static { $this->signedPdfPath = $v; return $this; }

    public function getRiskScore(): ?float { return $this->riskScore; }
    public function setRiskScore(?float $v): static { $this->riskScore = $v; return $this; }

    public function getRiskLevel(): ?string { return $this->riskLevel; }
    public function setRiskLevel(?string $v): static { $this->riskLevel = $v; return $this; }

    public function getCancellationReason(): ?string { return $this->cancellationReason; }
    public function setCancellationReason(?string $v): static { $this->cancellationReason = $v; return $this; }

    public function getCompletedAt(): ?\DateTimeInterface { return $this->completedAt; }
    public function setCompletedAt(?\DateTimeInterface $dt): static { $this->completedAt = $dt; return $this; }

    public function getCancelledAt(): ?\DateTimeInterface { return $this->cancelledAt; }
    public function setCancelledAt(?\DateTimeInterface $dt): static { $this->cancelledAt = $dt; return $this; }

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    // MILESTONES
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    // ══════════════════════════════════════════════
    // MILESTONES
    // ══════════════════════════════════════════════
    /** @return Collection<int, Milestone> */
    public function getMilestones(): Collection { return $this->milestones; }

    public function addMilestone(Milestone $milestone): static
    {
        if (!$this->milestones->contains($milestone)) {
            $this->milestones->add($milestone);
            $milestone->setContract($this);
        }
        return $this;
    }

    public function removeMilestone(Milestone $milestone): static
    {
        if ($this->milestones->removeElement($milestone)) {
            if ($milestone->getContract() === $this) {
                $milestone->setContract(null);
            }
        }
        return $this;
    }

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    // HELPERS
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    // ══════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════
    public function isFullySigned(): bool
    {
        return $this->clientSigned && $this->workerSigned;
    }

    public function isPendingSignature(): bool
    {
        return $this->status === self::STATUS_PENDING_SIGN;
    }

    public function canBeEdited(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBeSigned(): bool
    {
        if ($this->status === self::STATUS_PENDING_SIGN) {
            return true;
        }

        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_IN_PROGRESS], true)
            && !$this->isFullySigned();
        return $this->status === self::STATUS_PENDING_SIGN;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PENDING_SIGN, self::STATUS_ACTIVE, self::STATUS_IN_PROGRESS]);
    }

    /** Contract is in a final state (no new messages in messagerie, delete allowed). */
    public function isClosed(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_DISPUTED], true);
    }

    public function getProgressPercent(): int
    {
        if ($this->milestones->isEmpty()) {
            return 0;
        }
        $completed = $this->milestones->filter(fn(Milestone $m) => $m->getStatus() === Milestone::STATUS_COMPLETED)->count();
        return (int) round(($completed / $this->milestones->count()) * 100);
    }

    public function getCompletedMilestonesCount(): int
    {
        return $this->milestones->filter(fn(Milestone $m) => $m->getStatus() === Milestone::STATUS_COMPLETED)->count();
    }

    public function getUpfrontAmount(): string
    {
        $totalCents = $this->moneyToCents($this->agreedPrice ?? '0.00');
        $upfrontRatio = max(0.0, min(100.0, (float) $this->upfrontPercent)) / 100;
        $upfrontCents = (int) round($totalCents * $upfrontRatio);

        return number_format($upfrontCents / 100, 2, '.', '');
    }

    public function getRemainingAmount(): string
    {
        $totalCents = $this->moneyToCents($this->agreedPrice ?? '0.00');
        $releasedCents = $this->moneyToCents($this->releasedAmount);
        $remainingCents = max(0, $totalCents - $releasedCents);

        return number_format($remainingCents / 100, 2, '.', '');
    }

    public function hasPendingApprovals(): bool
    {
        return $this->milestones->exists(
            static fn (int $key, Milestone $milestone) => $milestone->getStatus() === Milestone::STATUS_DELIVERED
        );
    }

    public function areAllMilestonesFinalized(): bool
    {
        if ($this->milestones->isEmpty()) {
            return true;
        }

        return !$this->milestones->exists(
            static fn (int $key, Milestone $milestone) => !$milestone->isFinalized()
        );
    }

    public function markUpfrontPaid(): void
    {
        $this->upfrontPaid = true;
        $this->upfrontPaidAt = new \DateTime();

        if ($this->status === self::STATUS_ACTIVE) {
            $this->status = self::STATUS_IN_PROGRESS;
        }
    }

    public function releaseMilestoneAmount(Milestone $milestone): void
    {
        $amount = $milestone->getAmount();
        if ($amount === null || $amount === '') {
            return;
        }

        $totalCents = $this->moneyToCents($this->agreedPrice ?? '0.00');
        $releasedCents = $this->moneyToCents($this->releasedAmount);
        $milestoneCents = $this->moneyToCents($amount);

        $nextReleased = min($totalCents, $releasedCents + $milestoneCents);
        $this->releasedAmount = number_format($nextReleased / 100, 2, '.', '');
    }

    public function signByClient(string $signatureData, string $ip): void
    {
        $this->clientSigned = true;
        $this->clientSignedAt = new \DateTime();
        $this->clientSignatureIp = $ip;
        $this->clientSignatureData = $signatureData;
        $this->activateIfFullySigned();
    }

    public function signByWorker(string $signatureData, string $ip): void
    {
        $this->workerSigned = true;
        $this->workerSignedAt = new \DateTime();
        $this->workerSignatureIp = $ip;
        $this->workerSignatureData = $signatureData;
        $this->activateIfFullySigned();
    }

    private function activateIfFullySigned(): void
    {
        if ($this->isFullySigned() && $this->status === self::STATUS_PENDING_SIGN) {
            $this->status = self::STATUS_ACTIVE;
        }
    }

    public function complete(): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completedAt = new \DateTime();
    }

    public function cancel(string $reason): void
    {
        $this->status = self::STATUS_CANCELLED;
        $this->cancellationReason = $reason;
        $this->cancelledAt = new \DateTime();
    }
    private function moneyToCents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
