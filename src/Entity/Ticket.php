<?php

namespace App\Entity;

use App\Entity\Traits\BlameableTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\TicketRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\AssociationOverrides([
    new ORM\AssociationOverride(
        name: 'createdBy',
        inversedBy: 'tickets',
        joinColumns: [new ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: false)]
    ),
])]
class Ticket
{
    use BlameableTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $subject = '';

    #[ORM\Column(length: 50)]
    private string $status = 'OPEN';

    #[ORM\Column(length: 50)]
    private string $priority = 'NORMAL';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $resolution = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastMessageAt = null;

    #[ORM\Column]
    private int $messageCount = 0;

    #[ORM\Column]
    private bool $acknowledgedByAd = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $acknowledgedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $satisfactionRating = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $satisfactionComment = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $aiSentiment = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $aiUrgency = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $aiSuggestedPriority = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $aiSummary = null;

    #[ORM\ManyToOne(inversedBy: 'tickets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?CategoryTicket $category = null;

    /**
     * @var Collection<int, SubTicket>
     */
    #[ORM\OneToMany(targetEntity: SubTicket::class, mappedBy: 'ticket')]
    private Collection $subTickets;

    public function __construct()
    {
        $this->subTickets = new ArrayCollection();
        $this->initializeCreatedAt();
    }

   

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPriority(): ?string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    public function getResolution(): ?string
    {
        return $this->resolution;
    }

    public function setResolution(?string $resolution): static
    {
        $this->resolution = $resolution;

        return $this;
    }

    public function getLastMessageAt(): ?\DateTimeImmutable
    {
        return $this->lastMessageAt;
    }

    public function setLastMessageAt(?\DateTimeInterface $lastMessageAt): static
    {
        $this->lastMessageAt = $lastMessageAt === null
            ? null
            : ($lastMessageAt instanceof \DateTimeImmutable
                ? $lastMessageAt
                : \DateTimeImmutable::createFromInterface($lastMessageAt));

        return $this;
    }

    public function getMessageCount(): int
    {
        return $this->messageCount;
    }

    public function setMessageCount(int $messageCount): static
    {
        $this->messageCount = $messageCount;

        return $this;
    }

    public function isAcknowledgedByAd(): bool
    {
        return $this->acknowledgedByAd;
    }

    public function setAcknowledgedByAd(bool $acknowledgedByAd): static
    {
        $this->acknowledgedByAd = $acknowledgedByAd;

        return $this;
    }

    public function getAcknowledgedAt(): ?\DateTimeImmutable
    {
        return $this->acknowledgedAt;
    }

    public function setAcknowledgedAt(?\DateTimeInterface $acknowledgedAt): static
    {
        $this->acknowledgedAt = $acknowledgedAt === null
            ? null
            : ($acknowledgedAt instanceof \DateTimeImmutable
                ? $acknowledgedAt
                : \DateTimeImmutable::createFromInterface($acknowledgedAt));

        return $this;
    }

    public function getSatisfactionRating(): ?int
    {
        return $this->satisfactionRating;
    }

    public function setSatisfactionRating(?int $satisfactionRating): static
    {
        $this->satisfactionRating = $satisfactionRating;

        return $this;
    }

    public function getSatisfactionComment(): ?string
    {
        return $this->satisfactionComment;
    }

    public function setSatisfactionComment(?string $satisfactionComment): static
    {
        $this->satisfactionComment = $satisfactionComment;

        return $this;
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function setClosedAt(?\DateTimeInterface $closedAt): static
    {
        $this->closedAt = $closedAt === null
            ? null
            : ($closedAt instanceof \DateTimeImmutable
                ? $closedAt
                : \DateTimeImmutable::createFromInterface($closedAt));

        return $this;
    }

    public function getAiSentiment(): ?string
    {
        return $this->aiSentiment;
    }

    public function setAiSentiment(?string $aiSentiment): static
    {
        $this->aiSentiment = $aiSentiment;

        return $this;
    }

    public function getAiUrgency(): ?string
    {
        return $this->aiUrgency;
    }

    public function setAiUrgency(?string $aiUrgency): static
    {
        $this->aiUrgency = $aiUrgency;

        return $this;
    }

    public function getAiSuggestedPriority(): ?string
    {
        return $this->aiSuggestedPriority;
    }

    public function setAiSuggestedPriority(?string $aiSuggestedPriority): static
    {
        $this->aiSuggestedPriority = $aiSuggestedPriority;

        return $this;
    }

    public function getAiSummary(): ?string
    {
        return $this->aiSummary;
    }

    public function setAiSummary(?string $aiSummary): static
    {
        $this->aiSummary = $aiSummary;

        return $this;
    }

    public function getCategory(): ?CategoryTicket
    {
        return $this->category;
    }

    public function setCategory(?CategoryTicket $category): static
    {
        $this->category = $category;

        return $this;
    }

    /**
     * @return Collection<int, SubTicket>
     */
    public function getSubTickets(): Collection
    {
        return $this->subTickets;
    }

    public function addSubTicket(SubTicket $subTicket): static
    {
        if (!$this->subTickets->contains($subTicket)) {
            $this->subTickets->add($subTicket);
            $subTicket->setTicket($this);
        }

        return $this;
    }

    public function removeSubTicket(SubTicket $subTicket): static
    {
        if ($this->subTickets->removeElement($subTicket)) {
            // set the owning side to null (unless already changed)
            if ($subTicket->getTicket() === $this) {
                $subTicket->setTicket(null);
            }
        }

        return $this;
    }

    
}
