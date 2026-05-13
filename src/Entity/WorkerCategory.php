<?php

namespace App\Entity;

use App\Entity\Traits\BlameableTrait;
use App\Repository\WorkerCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkerCategoryRepository::class)]
#[ORM\HasLifecycleCallbacks]
class WorkerCategory
{
    use BlameableTrait;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->created_at = $now;
        $this->update_at = $now; // Note: Property name is update_at
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->update_at = new \DateTimeImmutable();
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $description = '';

    #[ORM\Column(length: 255)]
    private string $status = 'active';

    #[ORM\Column]
    private int $display_order = 0;

    #[ORM\Column]
    private int $total_workers = 0;

    #[ORM\Column(length: 255)]
    private string $icon = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $average_hourly_rate = '0.00';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $created_at;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $update_at;

    /**
     * @var Collection<int, WorkerProfile>
     */
    #[ORM\OneToMany(targetEntity: WorkerProfile::class, mappedBy: 'workerCategory')]
    private Collection $worker_profile;

    public function __construct()
    {
        $this->worker_profile = new ArrayCollection();
        $now = new \DateTimeImmutable();
        $this->created_at = $now;
        $this->update_at = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getDisplayOrder(): int
    {
        return $this->display_order;
    }

    public function setDisplayOrder(int $display_order): static
    {
        $this->display_order = $display_order;

        return $this;
    }

    public function getTotalWorkers(): int
    {
        return $this->total_workers;
    }

    public function setTotalWorkers(int $total_workers): static
    {
        $this->total_workers = $total_workers;

        return $this;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function setIcon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getAverageHourlyRate(): string
    {
        return $this->average_hourly_rate;
    }

    public function setAverageHourlyRate(string $average_hourly_rate): static
    {
        $this->average_hourly_rate = $average_hourly_rate;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->created_at;
    }

    public function getUpdateAt(): \DateTimeImmutable
    {
        return $this->update_at;
    }

    /**
     * @return Collection<int, WorkerProfile>
     */
    public function getWorkerProfile(): Collection
    {
        return $this->worker_profile;
    }

    public function addWorkerProfile(WorkerProfile $workerProfile): static
    {
        if (!$this->worker_profile->contains($workerProfile)) {
            $this->worker_profile->add($workerProfile);
            $workerProfile->setWorkerCategory($this);
        }

        return $this;
    }

    public function removeWorkerProfile(WorkerProfile $workerProfile): static
    {
        if ($this->worker_profile->removeElement($workerProfile)) {
            // set the owning side to null (unless already changed)
            if ($workerProfile->getWorkerCategory() === $this) {
                $workerProfile->setWorkerCategory(null);
            }
        }

        return $this;
    }
}
