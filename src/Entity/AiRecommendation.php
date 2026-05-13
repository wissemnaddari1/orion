<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\BlameableTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\AiRecommendationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AiRecommendationRepository::class)]
#[ORM\Table(name: 'ai_recommendation')]
#[ORM\Index(name: 'idx_ai_rec_service_created', columns: ['service_request_id', 'created_at'])]
#[ORM\Index(name: 'idx_ai_rec_service_user', columns: ['service_request_id', 'recommended_user_id'])]
#[ORM\HasLifecycleCallbacks]
class AiRecommendation
{
    use BlameableTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ServiceRequest::class)]
    #[ORM\JoinColumn(name: 'service_request_id', nullable: false, onDelete: 'CASCADE')]
    private ?ServiceRequest $serviceRequest = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'recommended_user_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $recommendedUser = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'requested_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $requestedBy = null;

    #[ORM\Column(type: Types::FLOAT)]
    private float $score = 0.0;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $explanations = [];

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $context = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getServiceRequest(): ?ServiceRequest
    {
        return $this->serviceRequest;
    }

    public function setServiceRequest(?ServiceRequest $serviceRequest): static
    {
        $this->serviceRequest = $serviceRequest;
        return $this;
    }

    public function getRecommendedUser(): ?User
    {
        return $this->recommendedUser;
    }

    public function setRecommendedUser(?User $recommendedUser): static
    {
        $this->recommendedUser = $recommendedUser;
        return $this;
    }

    public function getRequestedBy(): ?User
    {
        return $this->requestedBy;
    }

    public function setRequestedBy(?User $requestedBy): static
    {
        $this->requestedBy = $requestedBy;
        return $this;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function setScore(float $score): static
    {
        $this->score = $score;
        return $this;
    }

    /**
     * @return list<string>
     */
    public function getExplanations(): array
    {
        return $this->explanations;
    }

    /**
     * @param list<string> $explanations
     */
    public function setExplanations(array $explanations): static
    {
        $this->explanations = array_values($explanations);
        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getContext(): ?array
    {
        return $this->context;
    }

    /**
     * @param array<string, mixed>|null $context
     */
    public function setContext(?array $context): static
    {
        $this->context = $context;
        return $this;
    }

}

