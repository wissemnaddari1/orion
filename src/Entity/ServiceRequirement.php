<?php

namespace App\Entity;

use App\Repository\ServiceRequirementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServiceRequirementRepository::class)]
class ServiceRequirement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // ✅ FIXED: added inversedBy
    #[ORM\ManyToOne(inversedBy: 'requirements')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ServiceRequest $service = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $details = '';

    #[ORM\Column(length: 255)]
    private string $requirement_type = '';

    #[ORM\Column(length: 255)]
    private string $answer_format = '';

    #[ORM\Column(type: Types::JSON)]
    private array $options_json = [];

    #[ORM\Column]
    private bool $is_mandatory = false;

    #[ORM\Column(type: Types::INTEGER)]
    private int $priority_level = 1;

    public function getId(): ?int { return $this->id; }

    public function getService(): ?ServiceRequest { return $this->service; }
    public function setService(?ServiceRequest $service): static { $this->service = $service; return $this; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getDetails(): ?string { return $this->details; }
    public function setDetails(string $details): static { $this->details = $details; return $this; }

    public function getRequirementType(): ?string { return $this->requirement_type; }
    public function setRequirementType(string $requirement_type): static { $this->requirement_type = $requirement_type; return $this; }

    public function getAnswerFormat(): ?string { return $this->answer_format; }
    public function setAnswerFormat(string $answer_format): static { $this->answer_format = $answer_format; return $this; }

    public function getOptionsJson(): array { return $this->options_json; }
    public function setOptionsJson(array $options_json): static { $this->options_json = $options_json; return $this; }

    public function isMandatory(): ?bool { return $this->is_mandatory; }
    public function setIsMandatory(bool $is_mandatory): static { $this->is_mandatory = $is_mandatory; return $this; }

    public function getPriorityLevel(): int { return $this->priority_level; }
    public function setPriorityLevel(int $priority_level): static { $this->priority_level = $priority_level; return $this; }
}