<?php

namespace App\Entity;

use App\Entity\Traits\BlameableTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\ServiceRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: ServiceRequestRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ServiceRequest
{
    use BlameableTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $client = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?WorkerCategory $category = null;

    #[ORM\OneToMany(mappedBy: 'service', targetEntity: ServiceRequirement::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $requirements;

    #[ORM\OneToMany(mappedBy: 'serviceRequest', targetEntity: Offer::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $offers;
    
    public function __construct()
    {
        $this->requirements = new ArrayCollection();
        $this->offers = new ArrayCollection();
        $this->initializeCreatedAt();
    }

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $budget_min = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $budget_max = '0.00';

    // ✅ FIXED: added default 'OPEN'
    #[ORM\Column(type: 'string', length: 50, options: ['default' => 'OPEN'])]
    private string $status = 'OPEN';

    #[ORM\Column]
    private int $duration = 0;
    
    #[ORM\Column(type: 'string', length: 50, options: ['default' => 'Entry'])]
    private string $level = 'Entry';


    public function getId(): ?int { return $this->id; }

    public function getClient(): ?User { return $this->client; }
    public function setClient(?User $client): static { $this->client = $client; return $this; }

    public function getCategory(): ?WorkerCategory { return $this->category; }
    public function setCategory(?WorkerCategory $category): static { $this->category = $category; return $this; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getBudgetMin(): ?string { return $this->budget_min; }
    public function setBudgetMin(string $budget_min): static { $this->budget_min = $budget_min; return $this; }

    public function getBudgetMax(): ?string { return $this->budget_max; }
    public function setBudgetMax(string $budget_max): static { $this->budget_max = $budget_max; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getRequirements(): Collection { return $this->requirements; }

    // ✅ ADDED: missing getter for offers
    /** @return Collection<int, Offer> */
    public function getOffers(): Collection { return $this->offers; }

    public function getDuration(): ?int { return $this->duration; }
    public function setDuration(int $duration): static { $this->duration = $duration; return $this; }
        public function getLevel(): ?string
    {
        return $this->level;
    }

    public function setLevel(string $level): static
    {
        $this->level = $level;
        return $this;
    }
}