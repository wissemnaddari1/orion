<?php

namespace App\Entity;

use App\Entity\Traits\BlameableTrait;
use App\Repository\WorkerProfileRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkerProfileRepository::class)]
#[ORM\HasLifecycleCallbacks]
class WorkerProfile
{
    use BlameableTrait;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->created_at = $now;
        $this->updated_at = $now;
        
        // Data cleaning
        if ($this->title) {
            $this->title = $this->normalizeProfessionalTitle($this->title);
        }
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updated_at = new \DateTimeImmutable();
        
        // Data cleaning
        if ($this->title) {
            $this->title = $this->normalizeProfessionalTitle($this->title);
        }
    }
    
    // ... existing getter/setter methods ...
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $bio = '';

    #[ORM\Column(length: 255)]
    private string $hourly_rate = '0';

    #[ORM\Column]
    private int $experience_years = 0;

    #[ORM\Column(length: 255)]
    private string $location = '';

    #[ORM\Column]
    private bool $verified = false;

    #[ORM\Column(length: 255)]
    private string $availability_status = 'available';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $created_at;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updated_at;

    #[ORM\ManyToOne(inversedBy: 'worker_profile')]
    private ?WorkerCategory $workerCategory = null;

    #[ORM\Column(length: 20, nullable: true, options: ['default' => null], name: 'phone_number')]
    private ?string $phoneNumber = null;

    #[ORM\Column(length: 255, nullable: true, options: ['default' => null])]
    private ?string $email = null;

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

    public function getUserId(): ?int
    {
        return $this->user?->getId();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $this->normalizeProfessionalTitle($title);

        return $this;
    }

    public function getBio(): string
    {
        return $this->bio;
    }

    public function setBio(string $bio): static
    {
        $this->bio = $bio;

        return $this;
    }

    public function getHourlyRate(): string
    {
        return $this->hourly_rate;
    }

    public function setHourlyRate(string $hourly_rate): static
    {
        $this->hourly_rate = $hourly_rate;

        return $this;
    }

    public function getExperienceYears(): int
    {
        return $this->experience_years;
    }

    public function setExperienceYears(int $experience_years): static
    {
        $this->experience_years = $experience_years;

        return $this;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function setLocation(string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): static
    {
        $this->verified = $verified;

        return $this;
    }

    public function getAvailabilityStatus(): string
    {
        return $this->availability_status;
    }

    public function setAvailabilityStatus(string $availability_status): static
    {
        $this->availability_status = $availability_status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function getWorkerCategory(): ?WorkerCategory
    {
        return $this->workerCategory;
    }

    public function setWorkerCategory(?WorkerCategory $workerCategory): static
    {
        $this->workerCategory = $workerCategory;

        return $this;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber !== null ? trim($phoneNumber) : null;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email !== null ? strtolower(trim($email)) : null;

        return $this;
    }

    private function normalizeProfessionalTitle(string $title): string
    {
        $title = preg_replace('/\s+/', ' ', trim($title)) ?? trim($title);
        $title = ucwords(strtolower($title));

        $acronyms = ['Ai', 'Ui', 'Ux', 'Qa', 'Seo', 'Cto', 'Ceo', 'Cfo', 'Hr', 'It', 'Devops'];
        foreach ($acronyms as $acronym) {
            $title = preg_replace('/\b' . preg_quote($acronym, '/') . '\b/u', strtoupper($acronym), $title) ?? $title;
        }

        return $title;
    }
}
