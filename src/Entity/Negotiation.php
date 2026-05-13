<?php

namespace App\Entity;

use App\Entity\Traits\BlameableTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\NegotiationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NegotiationRepository::class)]
#[ORM\Table(name: 'negotiation')]
#[ORM\HasLifecycleCallbacks]
class Negotiation
{
    use BlameableTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $subject = null;

    #[ORM\Column(length: 20)]
    private string $status; // OPEN | COUNTERED | ACCEPTED | REJECTED | EXPIRED

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $counterPrice = null;

    #[ORM\Column(nullable: true)]
    private ?int $timelineDays = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $scopeDetails = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $deliverablesList = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $acceptanceCriteria = null;

    #[ORM\Column(nullable: true)]
    private ?int $includedRevisions = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $extraRevisionFee = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $priorityLevel = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $meetingFrequency = null;

    #[ORM\Column]
    private bool $ndaRequired = false;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $dataSensitivityLevel = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $latePenaltyPercent = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $expiresAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastActionAt = null;

    /* ===================== RELATIONS ===================== */

    #[ORM\OneToOne(inversedBy: 'negotiation')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Offer $offer = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $openedBy = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $targetUser = null;

    public function __construct()
    {
        $this->initializeCreatedAt();
    }

    /* ===================== GETTERS & SETTERS ===================== */

    public function getId(): ?int
    {
        return $this->id;
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

    public function getOffer(): ?Offer
    {
        return $this->offer;
    }

    public function setOffer(Offer $offer): self
    {
        $this->offer = $offer;
        return $this;
    }

    public function getOpenedBy(): ?User
    {
        return $this->openedBy;
    }

    public function setOpenedBy(User $openedBy): self
    {
        $this->openedBy = $openedBy;
        return $this;
    }

    public function getTargetUser(): ?User
    {
        return $this->targetUser;
    }

    public function setTargetUser(User $targetUser): self
    {
        $this->targetUser = $targetUser;
        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function getCounterPrice(): ?string
    {
        return $this->counterPrice;
    }

    public function setCounterPrice(?string $counterPrice): self
    {
        $this->counterPrice = $counterPrice;
        return $this;
    }

    public function getTimelineDays(): ?int
    {
        return $this->timelineDays;
    }

    public function setTimelineDays(?int $timelineDays): self
    {
        $this->timelineDays = $timelineDays;
        return $this;
    }

    public function getScopeDetails(): ?string
    {
        return $this->scopeDetails;
    }

    public function setScopeDetails(?string $scopeDetails): self
    {
        $this->scopeDetails = $scopeDetails;
        return $this;
    }

    public function getDeliverablesList(): ?string
    {
        return $this->deliverablesList;
    }

    public function setDeliverablesList(?string $deliverablesList): self
    {
        $this->deliverablesList = $deliverablesList;
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

    public function getIncludedRevisions(): ?int
    {
        return $this->includedRevisions;
    }

    public function setIncludedRevisions(?int $includedRevisions): self
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

    public function getPriorityLevel(): ?string
    {
        return $this->priorityLevel;
    }

    public function setPriorityLevel(?string $priorityLevel): self
    {
        $this->priorityLevel = $priorityLevel;
        return $this;
    }

    public function getMeetingFrequency(): ?string
    {
        return $this->meetingFrequency;
    }

    public function setMeetingFrequency(?string $meetingFrequency): self
    {
        $this->meetingFrequency = $meetingFrequency;
        return $this;
    }

    public function isNdaRequired(): bool
    {
        return $this->ndaRequired;
    }

    public function setNdaRequired(bool $ndaRequired): self
    {
        $this->ndaRequired = $ndaRequired;
        return $this;
    }

    public function getDataSensitivityLevel(): ?string
    {
        return $this->dataSensitivityLevel;
    }

    public function setDataSensitivityLevel(?string $dataSensitivityLevel): self
    {
        $this->dataSensitivityLevel = $dataSensitivityLevel;
        return $this;
    }

    public function getLatePenaltyPercent(): ?string
    {
        return $this->latePenaltyPercent;
    }

    public function setLatePenaltyPercent(?string $latePenaltyPercent): self
    {
        $this->latePenaltyPercent = $latePenaltyPercent;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeInterface $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function getLastActionAt(): ?\DateTimeInterface
    {
        return $this->lastActionAt;
    }

    public function setLastActionAt(?\DateTimeInterface $lastActionAt): self
    {
        $this->lastActionAt = $lastActionAt;
        return $this;
    }
}
