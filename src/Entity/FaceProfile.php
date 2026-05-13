<?php

namespace App\Entity;

use App\Entity\Traits\BlameableTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\FaceProfileRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FaceProfileRepository::class)]
#[ORM\Table(name: 'face_profiles')]
#[ORM\HasLifecycleCallbacks]
class FaceProfile
{
    use BlameableTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /**
     * Face embedding from local Python service (array of floats, e.g. 128-dim).
     *
     * @var array<int, float>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $embedding = [];

    #[ORM\Column(name: 'last_matched_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastMatchedAt = null;

    public function __construct()
    {
        $this->initializeCreatedAt();
    }

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

    /**
     * @return array<int, float>
     */
    public function getEmbedding(): array
    {
        return $this->embedding;
    }

    /**
     * @param array<int, float> $embedding
     */
    public function setEmbedding(array $embedding): static
    {
        $this->embedding = $embedding;
        return $this;
    }

    public function getLastMatchedAt(): ?\DateTimeInterface
    {
        return $this->lastMatchedAt;
    }

    public function setLastMatchedAt(?\DateTimeInterface $lastMatchedAt): static
    {
        $this->lastMatchedAt = $lastMatchedAt;
        return $this;
    }

}
