<?php

namespace App\Entity;

use App\Entity\Traits\BlameableTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\MilestoneRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MilestoneRepository::class)]
#[ORM\Table(name: 'milestone')]
#[ORM\UniqueConstraint(name: 'uq_milestone_order', columns: ['contract_id', 'order_index'])]
#[ORM\HasLifecycleCallbacks]
class Milestone
{
    use BlameableTrait;
    use TimestampableTrait;

    // Status constants
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_DELIVERED = 'DELIVERED';
    public const STATUS_REVISION_REQUESTED = 'REVISION_REQUESTED';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_DELIVERED,
        self::STATUS_REVISION_REQUESTED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Contract::class, inversedBy: 'milestones')]
    #[ORM\JoinColumn(name: 'contract_id', nullable: false, onDelete: 'CASCADE')]
    private ?Contract $contract = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $dueDate;

    #[ORM\Column(type: Types::INTEGER)]
    private int $orderIndex = 0;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'PENDING'])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $amount = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deliveredAt = null;

    public function __construct()
    {
        $this->initializeCreatedAt();
        $this->dueDate = new \DateTime('today');
    }

    // â”€â”€ Getters / Setters â”€â”€

    public function getId(): ?int { return $this->id; }

    public function getContract(): ?Contract { return $this->contract; }
    public function setContract(?Contract $contract): static { $this->contract = $contract; return $this; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getDueDate(): ?\DateTimeInterface { return $this->dueDate; }
    public function setDueDate(\DateTimeInterface $dueDate): static { $this->dueDate = $dueDate; return $this; }

    public function getOrderIndex(): ?int { return $this->orderIndex; }
    public function setOrderIndex(int $orderIndex): static { $this->orderIndex = $orderIndex; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getAmount(): ?string { return $this->amount; }
    public function setAmount(?string $amount): static { $this->amount = $amount; return $this; }

    public function getCompletedAt(): ?\DateTimeInterface { return $this->completedAt; }
    public function setCompletedAt(?\DateTimeInterface $dt): static { $this->completedAt = $dt; return $this; }

    public function getDeliveredAt(): ?\DateTimeInterface { return $this->deliveredAt; }
    public function setDeliveredAt(?\DateTimeInterface $dt): static { $this->deliveredAt = $dt; return $this; }

    // â”€â”€ Helpers â”€â”€

    public function isCompleted(): bool { return $this->status === self::STATUS_COMPLETED; }
    public function isPending(): bool { return $this->status === self::STATUS_PENDING; }
    public function isDelivered(): bool { return $this->status === self::STATUS_DELIVERED; }
    public function isRevisionRequested(): bool { return $this->status === self::STATUS_REVISION_REQUESTED; }
    public function isFinalized(): bool { return \in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED], true); }

    public function markCompleted(): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completedAt = new \DateTime();
    }

    public function markDelivered(): void
    {
        $this->status = self::STATUS_DELIVERED;
        $this->deliveredAt = new \DateTime();
    }

    public function requestRevision(): void
    {
        $this->status = self::STATUS_REVISION_REQUESTED;
        $this->completedAt = null;
    }

    public function isOverdue(): bool
    {
        if ($this->isFinalized() || $this->isDelivered() || $this->dueDate === null) {
            return false;
        }
        return $this->dueDate < new \DateTime('today');
    }
}
