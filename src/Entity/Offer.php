<?php

namespace App\Entity;

use App\Entity\Traits\BlameableTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\OfferRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OfferRepository::class)]
#[ORM\Table(name: 'offer')]
#[ORM\Index(name: 'idx_offer_status', columns: ['status'])]
#[ORM\Index(name: 'idx_offer_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_offer_price', columns: ['price'])]
#[ORM\Index(name: 'idx_offer_estimated_time', columns: ['estimated_time_days'])]
#[ORM\HasLifecycleCallbacks]
class Offer
{
    use BlameableTrait;
    use TimestampableTrait;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_ACCEPTED = 'ACCEPTED';
    public const STATUS_DECLINED = 'DECLINED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_NEGOTIATING = 'NEGOTIATING';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $price;

    #[ORM\Column]
    private int $estimatedTimeDays;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $scopeSummary = null;

    #[ORM\Column(length: 20)]
    private string $status; // PENDING | ACCEPTED | REJECTED | DECLINED | NEGOTIATING | EXPIRED

    /** AI matchmaking score (0â€“1), nullable for manually created offers. */
    #[ORM\Column(name: 'match_score', type: Types::FLOAT, nullable: true)]
    private ?float $matchScore = null;

    #[ORM\Column(name: 'proposed_budget', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $proposedBudget = null;

    #[ORM\Column(name: 'proposed_deadline', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $proposedDeadline = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $deliverables = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $acceptanceCriteria = null;

    #[ORM\Column]
    private int $includedRevisions = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $extraRevisionFee = null;

    #[ORM\Column(nullable: true)]
    private ?int $responseSlaHours = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $startDateAvailable = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $deliveryDateEstimated = null;

    #[ORM\Column(length: 20)]
    private string $priorityLevel; // LOW | MEDIUM | HIGH

    #[ORM\Column]
    private bool $isUrgent = false;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $rushFee = null;

    /* ===================== RELATIONS ===================== */

    #[ORM\OneToOne(mappedBy: 'offer', targetEntity: Negotiation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?Negotiation $negotiation = null;

    #[ORM\ManyToOne(inversedBy: 'offers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ServiceRequest $serviceRequest = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $worker = null;

    /** Client who owns the service request; optional for backward compat, derived from serviceRequest->client if null. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $client = null;

    public function __construct()
    {
        $this->initializeCreatedAt();
    }

    public function getNegotiation(): ?Negotiation
    {
        return $this->negotiation;
    }

    public function setNegotiation(?Negotiation $negotiation): self
    {
        $this->negotiation = $negotiation;
        return $this;
    }

    /* ===================== GETTERS & SETTERS ===================== */

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function getEstimatedTimeDays(): int
    {
        return $this->estimatedTimeDays;
    }

    public function setEstimatedTimeDays(int $estimatedTimeDays): self
    {
        $this->estimatedTimeDays = $estimatedTimeDays;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getServiceRequest(): ?ServiceRequest
    {
        return $this->serviceRequest;
    }

    public function setServiceRequest(ServiceRequest $serviceRequest): self
    {
        $this->serviceRequest = $serviceRequest;
        return $this;
    }

    public function getWorker(): ?User
    {
        return $this->worker;
    }

    public function setWorker(User $worker): self
    {
        $this->worker = $worker;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getScopeSummary(): ?string
    {
        return $this->scopeSummary;
    }

    public function setScopeSummary(?string $scopeSummary): self
    {
        $this->scopeSummary = $scopeSummary;
        return $this;
    }

    public function getDeliverables(): ?string
    {
        return $this->deliverables;
    }

    public function setDeliverables(?string $deliverables): self
    {
        $this->deliverables = $deliverables;
        return $this;
    }

    public function getAcceptanceCriteria(): ?string
    {
        return $this->acceptanceCriteria;
    }

    public function setAcceptanceCriteria(?string $acceptanceCriteria): self
    {
        $this->acceptanceCriteria = $acceptanceCriteria;
        return $this;
    }

    public function getIncludedRevisions(): int
    {
        return $this->includedRevisions;
    }

    public function setIncludedRevisions(int $includedRevisions): self
    {
        $this->includedRevisions = $includedRevisions;
        return $this;
    }

    public function getExtraRevisionFee(): ?string
    {
        return $this->extraRevisionFee;
    }

    public function setExtraRevisionFee(?string $extraRevisionFee): self
    {
        $this->extraRevisionFee = $extraRevisionFee;
        return $this;
    }

    public function getPriorityLevel(): string
    {
        return $this->priorityLevel;
    }

    public function setPriorityLevel(string $priorityLevel): self
    {
        $this->priorityLevel = $priorityLevel;
        return $this;
    }

    public function isUrgent(): bool
    {
        return $this->isUrgent;
    }

    public function setIsUrgent(bool $isUrgent): self
    {
        $this->isUrgent = $isUrgent;
        return $this;
    }

    public function getRushFee(): ?string
    {
        return $this->rushFee;
    }

    public function setRushFee(?string $rushFee): self
    {
        $this->rushFee = $rushFee;
        return $this;
    }

    public function getResponseSlaHours(): ?int
    {
        return $this->responseSlaHours;
    }

    public function setResponseSlaHours(?int $responseSlaHours): self
    {
        $this->responseSlaHours = $responseSlaHours;
        return $this;
    }

    public function getStartDateAvailable(): ?\DateTimeInterface
    {
        return $this->startDateAvailable;
    }

    public function setStartDateAvailable(?\DateTimeInterface $startDateAvailable): self
    {
        $this->startDateAvailable = $startDateAvailable;
        return $this;
    }

    public function getDeliveryDateEstimated(): ?\DateTimeInterface
    {
        return $this->deliveryDateEstimated;
    }

    public function setDeliveryDateEstimated(?\DateTimeInterface $deliveryDateEstimated): self
    {
        $this->deliveryDateEstimated = $deliveryDateEstimated;
        return $this;
    }

    public function getClient(): ?User
    {
        return $this->client ?? $this->serviceRequest?->getClient();
    }

    public function setClient(?User $client): self
    {
        $this->client = $client;
        return $this;
    }

    public function getMatchScore(): ?float
    {
        return $this->matchScore;
    }

    public function setMatchScore(?float $matchScore): self
    {
        $this->matchScore = $matchScore;
        return $this;
    }

    public function getProposedBudget(): ?string
    {
        return $this->proposedBudget;
    }

    public function setProposedBudget(?string $proposedBudget): self
    {
        $this->proposedBudget = $proposedBudget;
        return $this;
    }

    public function getProposedDeadline(): ?\DateTimeInterface
    {
        return $this->proposedDeadline;
    }

    public function setProposedDeadline(?\DateTimeInterface $proposedDeadline): self
    {
        $this->proposedDeadline = $proposedDeadline;
        return $this;
    }

    /** Alias for getWorker() for matchmaking/freelancer terminology. */
    public function getFreelancer(): ?User
    {
        return $this->worker;
    }
}
