<?php

namespace App\Entity;

use App\Entity\Traits\BlameableTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\ConversationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConversationRepository::class)]
#[ORM\Table(name: 'conversation')]
#[ORM\Index(columns: ['contract_id'], name: 'idx_conversation_contract_id')]
#[ORM\HasLifecycleCallbacks]
class Conversation
{
    use BlameableTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Contract::class)]
    #[ORM\JoinColumn(name: 'contract_id', referencedColumnName: 'id', nullable: false, unique: true)]
    private ?Contract $contract = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'client_id', referencedColumnName: 'id', nullable: false)]
    private ?User $client = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'worker_id', referencedColumnName: 'id', nullable: false)]
    private ?User $worker = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastMessageAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedByClientAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedByWorkerAt = null;

    /** @var Collection<int, ConversationMessage> */
    #[ORM\OneToMany(targetEntity: ConversationMessage::class, mappedBy: 'conversation', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $messages;

    public function __construct()
    {
        $this->messages = new ArrayCollection();
        $this->initializeCreatedAt();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContract(): ?Contract
    {
        return $this->contract;
    }

    public function setContract(?Contract $contract): static
    {
        $this->contract = $contract;
        return $this;
    }

    public function getClient(): ?User
    {
        return $this->client;
    }

    public function setClient(?User $client): static
    {
        $this->client = $client;
        return $this;
    }

    public function getWorker(): ?User
    {
        return $this->worker;
    }

    public function setWorker(?User $worker): static
    {
        $this->worker = $worker;
        return $this;
    }

    public function getLastMessageAt(): ?\DateTimeInterface
    {
        return $this->lastMessageAt;
    }

    public function setLastMessageAt(?\DateTimeInterface $lastMessageAt): static
    {
        $this->lastMessageAt = $lastMessageAt;
        return $this;
    }

    public function getDeletedByClientAt(): ?\DateTimeInterface
    {
        return $this->deletedByClientAt;
    }

    public function setDeletedByClientAt(?\DateTimeInterface $deletedByClientAt): static
    {
        $this->deletedByClientAt = $deletedByClientAt;
        return $this;
    }

    public function getDeletedByWorkerAt(): ?\DateTimeInterface
    {
        return $this->deletedByWorkerAt;
    }

    public function setDeletedByWorkerAt(?\DateTimeInterface $deletedByWorkerAt): static
    {
        $this->deletedByWorkerAt = $deletedByWorkerAt;
        return $this;
    }

    /** @return Collection<int, ConversationMessage> */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function isParticipant(User $user): bool
    {
        return $this->client && $this->client->getId() === $user->getId()
            || $this->worker && $this->worker->getId() === $user->getId();
    }

    public function isDeletedBy(User $user): bool
    {
        if ($this->client && $this->client->getId() === $user->getId()) {
            return $this->deletedByClientAt !== null;
        }
        if ($this->worker && $this->worker->getId() === $user->getId()) {
            return $this->deletedByWorkerAt !== null;
        }
        return false;
    }

    public function markDeletedBy(User $user): void
    {
        if ($this->client && $this->client->getId() === $user->getId()) {
            $this->deletedByClientAt = new \DateTime();
        }
        if ($this->worker && $this->worker->getId() === $user->getId()) {
            $this->deletedByWorkerAt = new \DateTime();
        }
    }

    public function isClosed(): bool
    {
        return $this->contract !== null && $this->contract->isClosed();
    }

    public function getOtherParticipant(User $user): ?User
    {
        if ($this->client && $this->client->getId() === $user->getId()) {
            return $this->worker;
        }
        if ($this->worker && $this->worker->getId() === $user->getId()) {
            return $this->client;
        }
        return null;
    }
}
