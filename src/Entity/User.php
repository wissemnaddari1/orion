<?php

namespace App\Entity;

use App\Entity\Traits\BlameableTrait;
use App\Enum\CertificateStatus;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Enum\WalletCurrency;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'This email is already in use.')]
#[UniqueEntity(fields: ['username'], message: 'This username is already in use.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use BlameableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 50)]
    #[Assert\Regex(
        pattern: '/^[A-Za-z0-9._-]+$/',
        message: 'Username can only contain letters, numbers, dots, underscores, and dashes.'
    )]
    private string $username = '';

    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private string $email = '';

    #[ORM\Column(name: 'password_hash', type: Types::STRING, length: 255)]
    #[Ignore]
    private string $passwordHash = '';

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [UserRole::SUPER_ADMIN->value, UserRole::ADMIN->value, UserRole::CLIENT->value, UserRole::WORKER->value])]
    private string $role = UserRole::CLIENT->value;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    #[Assert\Length(max: 25)]
    #[Assert\Regex(
        pattern: '/^(\+?216)?\d{8}$|^\+?[1-9]\d{6,14}$/',
        message: 'Please enter a valid Tunisian or international phone number.'
    )]
    private ?string $phone = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [UserStatus::ACTIVE->value, UserStatus::SUSPENDED->value, UserStatus::PENDING->value, UserStatus::BANNED->value])]
    private string $status = UserStatus::PENDING->value;

    #[ORM\Column(name: 'first_name', type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $firstName = '';

    #[ORM\Column(name: 'last_name', type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $lastName = '';

    #[ORM\Column(name: 'profile_picture', type: Types::STRING, length: 255, nullable: true)]
    private ?string $profilePicture = null;

    #[ORM\Column(name: 'email_verified', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $emailVerified = false;

    #[ORM\Column(name: 'phone_verified', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $phoneVerified = false;

    #[ORM\Column(name: 'two_factor_enabled', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $twoFactorEnabled = false;

    #[ORM\Column(name: 'two_factor_secret', type: Types::STRING, length: 512, nullable: true)]
    #[Ignore]
    private ?string $twoFactorSecret = null;

    #[ORM\Column(name: 'two_factor_temp_secret', type: Types::STRING, length: 512, nullable: true)]
    #[Ignore]
    private ?string $twoFactorTempSecret = null;

    #[ORM\Column(name: 'two_factor_backup_codes', type: Types::JSON, nullable: true)]
    private ?array $twoFactorBackupCodes = null;

    #[ORM\Column(name: 'two_factor_failed_attempts', type: Types::INTEGER, options: ['default' => 0])]
    private int $twoFactorFailedAttempts = 0;

    #[ORM\Column(name: 'two_factor_locked_until', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $twoFactorLockedUntil = null;

    #[ORM\Column(name: 'last_ip', type: Types::STRING, length: 255, nullable: true)]
    private ?string $lastIp = null;

    #[ORM\Column(name: 'last_login', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastLogin = null;

    // Facial Recognition fields
    #[ORM\Column(name: 'face_image_path', type: Types::STRING, length: 255, nullable: true)]
    private ?string $faceImagePath = null;

    #[ORM\Column(name: 'face_embedding', type: Types::BLOB, nullable: true)]
    private $faceEmbedding = null;

    #[ORM\Column(name: 'face_last_verified', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $faceLastVerified = null;

    #[ORM\Column(name: 'face_model_version', type: Types::STRING, length: 255, nullable: true)]
    private ?string $faceModelVersion = null;

    #[ORM\Column(name: 'face_failed_attempts', type: Types::INTEGER, nullable: true, options: ['default' => 0])]
    private ?int $faceFailedAttempts = 0;

    #[ORM\Column(name: 'face_locked_until', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $faceLockedUntil = null;

    #[ORM\Column(name: 'face_enrolled_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $faceEnrolledAt = null;

    // Login lockout (anti-bruteforce)
    #[ORM\Column(name: 'failed_login_attempts', type: Types::INTEGER, options: ['default' => 0])]
    private int $failedLoginAttempts = 0;

    #[ORM\Column(name: 'login_locked_until', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $loginLockedUntil = null;

    #[ORM\Column(name: 'last_failed_login_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastFailedLoginAt = null;

    // Admin ban (current snapshot)
    #[ORM\Column(name: 'is_banned', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isBanned = false;

    #[ORM\Column(name: 'ban_reason', type: Types::TEXT, nullable: true)]
    private ?string $banReason = null;

    #[ORM\Column(name: 'ban_note', type: Types::TEXT, nullable: true)]
    private ?string $banNote = null;

    #[ORM\Column(name: 'banned_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $bannedAt = null;

    #[ORM\Column(name: 'ban_ends_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $banEndsAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'banned_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $bannedBy = null;

    #[ORM\Column(name: 'ban_type', type: Types::STRING, length: 10, nullable: true)]
    private ?string $banType = null;

    #[ORM\Column(name: 'ban_count', type: Types::INTEGER, options: ['default' => 0])]
    private int $banCount = 0;

    // Wallet fields
    #[ORM\Column(name: 'account_balance', type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\Type(type: 'numeric', message: 'Account balance must be a numeric value.')]
    #[Assert\PositiveOrZero]
    private string $accountBalance = '0.00';

    #[ORM\Column(name: 'wallet_currency', type: Types::STRING, length: 3, options: ['default' => 'USD'])]
    #[Assert\Choice(choices: [WalletCurrency::TND->value, WalletCurrency::EUR->value, WalletCurrency::USD->value])]
    private string $walletCurrency = WalletCurrency::USD->value;

    // Rating fields
    #[ORM\Column(name: 'rating_avg', type: Types::DECIMAL, precision: 3, scale: 2, nullable: true)]
    #[Assert\Type(type: 'numeric', message: 'Rating must be a numeric value.')]
    #[Assert\PositiveOrZero]
    private ?string $ratingAvg = null;

    #[ORM\Column(name: 'total_reviews', type: Types::INTEGER, nullable: true, options: ['default' => 0])]
    private ?int $totalReviews = 0;

    // Location fields
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    #[Assert\Regex(
        pattern: '/^[A-Za-z_]+\/[A-Za-z_]+$/',
        message: 'Timezone must be a valid region identifier like "Africa/Tunis".'
    )]
    private ?string $timezone = null;

    // Certificate fields (for freelancer verification)
    #[ORM\Column(name: 'certificate_path', type: Types::STRING, length: 255, nullable: true)]
    private ?string $certificatePath = null;

    #[ORM\Column(name: 'certificate_ai_score', type: Types::INTEGER, nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?int $certificateAiScore = null;

    #[ORM\Column(name: 'certificate_ai_verdict', type: Types::STRING, length: 20, nullable: true)]
    #[Assert\Choice(choices: ['pending', 'valid', 'fake', 'suspicious'])]
    private ?string $certificateAiVerdict = 'pending';

    #[ORM\Column(name: 'certificate_status', type: Types::STRING, length: 20, nullable: true)]
    #[Assert\Choice(choices: [CertificateStatus::PENDING->value, CertificateStatus::APPROVED->value, CertificateStatus::REJECTED->value])]
    private ?string $certificateStatus = null;

    #[ORM\Column(name: 'certificate_uploaded_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $certificateUploadedAt = null;

    #[ORM\Column(name: 'certificate_approved_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $certificateApprovedAt = null;

    #[ORM\Column(name: 'certificate_review_note', type: Types::TEXT, nullable: true)]
    private ?string $certificateReviewNote = null;

    #[ORM\Column(name: 'certificate_extracted_text', type: Types::TEXT, nullable: true)]
    private ?string $certificateExtractedText = null;

    // Email verification fields (no separate table)
    #[ORM\Column(name: 'email_verification_code', type: Types::STRING, length: 6, nullable: true)]
    private ?string $emailVerificationCode = null;

    #[ORM\Column(name: 'email_verification_expires_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $emailVerificationExpiresAt = null;

    // Timestamps
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, Ticket>
     */
    #[ORM\OneToMany(targetEntity: Ticket::class, mappedBy: 'createdBy')]
    private Collection $tickets;

    /**
     * @var Collection<int, SubTicket>
     */
    #[ORM\OneToMany(targetEntity: SubTicket::class, mappedBy: 'sender')]
    private Collection $subTickets;

    public function __construct()
    {
        $this->status = UserStatus::PENDING->value;
        $this->role = UserRole::CLIENT->value;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->tickets = new ArrayCollection();
        $this->subTickets = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }


    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getPasswordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(#[\SensitiveParameter] string $passwordHash): self
    {
        $this->passwordHash = $passwordHash;
        return $this;
    }

    public function getRole(): UserRole
    {
        return UserRole::tryFrom($this->role) ?? UserRole::CLIENT;
    }

    public function setRole(UserRole|string $role): self
    {
        $this->role = $role instanceof UserRole ? $role->value : (UserRole::tryFrom(strtoupper($role))?->value ?? UserRole::CLIENT->value);
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }

    public function getStatus(): UserStatus
    {
        return UserStatus::tryFrom($this->status) ?? UserStatus::PENDING;
    }

    public function setStatus(UserStatus|string $status): self
    {
        $this->status = $status instanceof UserStatus ? $status->value : (UserStatus::tryFrom(strtoupper($status))?->value ?? UserStatus::PENDING->value);
        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getProfilePicture(): ?string
    {
        return $this->profilePicture;
    }

    public function setProfilePicture(?string $profilePicture): self
    {
        $this->profilePicture = $profilePicture;
        return $this;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    public function setEmailVerified(bool $emailVerified): self
    {
        $this->emailVerified = $emailVerified;
        return $this;
    }

    public function isPhoneVerified(): bool
    {
        return $this->phoneVerified;
    }

    public function setPhoneVerified(bool $phoneVerified): self
    {
        $this->phoneVerified = $phoneVerified;
        return $this;
    }

    public function isTwoFactorEnabled(): bool
    {
        return $this->twoFactorEnabled;
    }

    public function setTwoFactorEnabled(bool $twoFactorEnabled): self
    {
        $this->twoFactorEnabled = $twoFactorEnabled;
        return $this;
    }

    public function getTwoFactorSecret(): ?string
    {
        return $this->twoFactorSecret;
    }

    public function setTwoFactorSecret(#[\SensitiveParameter] ?string $twoFactorSecret): self
    {
        $this->twoFactorSecret = $twoFactorSecret;
        return $this;
    }

    public function getTwoFactorTempSecret(): ?string
    {
        return $this->twoFactorTempSecret;
    }

    public function setTwoFactorTempSecret(#[\SensitiveParameter] ?string $twoFactorTempSecret): self
    {
        $this->twoFactorTempSecret = $twoFactorTempSecret;
        return $this;
    }

    /** @return string[]|null */
    public function getTwoFactorBackupCodes(): ?array
    {
        return $this->twoFactorBackupCodes;
    }

    /** @param string[]|null $twoFactorBackupCodes */
    public function setTwoFactorBackupCodes(?array $twoFactorBackupCodes): self
    {
        $this->twoFactorBackupCodes = $twoFactorBackupCodes;
        return $this;
    }

    public function getTwoFactorFailedAttempts(): int
    {
        return $this->twoFactorFailedAttempts;
    }

    public function setTwoFactorFailedAttempts(int $twoFactorFailedAttempts): self
    {
        $this->twoFactorFailedAttempts = $twoFactorFailedAttempts;
        return $this;
    }

    public function getTwoFactorLockedUntil(): ?\DateTimeInterface
    {
        return $this->twoFactorLockedUntil;
    }

    /** @internal Doctrine / lifecycle only. */
    protected function setTwoFactorLockedUntil(?\DateTimeInterface $twoFactorLockedUntil): self
    {
        $this->twoFactorLockedUntil = $twoFactorLockedUntil;
        return $this;
    }

    public function isTwoFactorLocked(): bool
    {
        if ($this->twoFactorLockedUntil === null) {
            return false;
        }
        return $this->twoFactorLockedUntil > new \DateTime();
    }

    public function incrementTwoFactorFailedAttempts(int $lockAfterAttempts = 5, int $lockSeconds = 30): self
    {
        $this->twoFactorFailedAttempts++;
        if ($this->twoFactorFailedAttempts >= $lockAfterAttempts) {
            $this->twoFactorLockedUntil = new \DateTime('+' . $lockSeconds . ' seconds');
        }
        return $this;
    }

    public function resetTwoFactorFailedAttempts(): self
    {
        $this->twoFactorFailedAttempts = 0;
        $this->twoFactorLockedUntil = null;
        return $this;
    }

    public function getLastIp(): ?string
    {
        return $this->lastIp;
    }

    public function setLastIp(?string $lastIp): self
    {
        $this->lastIp = $lastIp;
        return $this;
    }

    public function getLastLogin(): ?\DateTimeInterface
    {
        return $this->lastLogin;
    }

    /** @internal Doctrine / lifecycle only; use recordLastLogin() to set from app code. */
    protected function setLastLogin(?\DateTimeInterface $lastLogin): self
    {
        $this->lastLogin = $lastLogin;
        return $this;
    }

    public function recordLastLogin(?\DateTimeInterface $when = null): self
    {
        $this->lastLogin = $when ?? new \DateTime();
        return $this;
    }

    // Facial Recognition getters/setters

    public function getFaceImagePath(): ?string
    {
        return $this->faceImagePath;
    }

    public function setFaceImagePath(?string $faceImagePath): self
    {
        $this->faceImagePath = $faceImagePath;
        return $this;
    }

    public function getFaceEmbedding(): ?string
    {
        if ($this->faceEmbedding === null) {
            return null;
        }

        // Handle Doctrine stream resource
        if (is_resource($this->faceEmbedding)) {
            return stream_get_contents($this->faceEmbedding, -1, 0);
        }

        return $this->faceEmbedding;
    }

    public function setFaceEmbedding(?string $faceEmbedding): self
    {
        $this->faceEmbedding = $faceEmbedding;
        return $this;
    }

    public function getFaceLastVerified(): ?\DateTimeInterface
    {
        return $this->faceLastVerified;
    }

    /** @internal Doctrine / lifecycle only; use recordFaceLastVerified() from app code. */
    protected function setFaceLastVerified(?\DateTimeInterface $faceLastVerified): self
    {
        $this->faceLastVerified = $faceLastVerified;
        return $this;
    }

    public function recordFaceLastVerified(?\DateTimeInterface $when = null): self
    {
        $this->faceLastVerified = $when;
        return $this;
    }

    public function getFaceModelVersion(): ?string
    {
        return $this->faceModelVersion;
    }

    public function setFaceModelVersion(?string $faceModelVersion): self
    {
        $this->faceModelVersion = $faceModelVersion;
        return $this;
    }

    public function getFaceFailedAttempts(): ?int
    {
        return $this->faceFailedAttempts;
    }

    public function setFaceFailedAttempts(?int $faceFailedAttempts): self
    {
        $this->faceFailedAttempts = $faceFailedAttempts;
        return $this;
    }

    public function incrementFaceFailedAttempts(): self
    {
        $this->faceFailedAttempts = ($this->faceFailedAttempts ?? 0) + 1;

        // Lock account after 3 failed attempts for 10 minutes
        if ($this->faceFailedAttempts >= 3) {
            $this->faceLockedUntil = new \DateTime('+10 minutes');
        }

        return $this;
    }

    public function resetFaceFailedAttempts(): self
    {
        $this->faceFailedAttempts = 0;
        $this->faceLockedUntil = null;
        return $this;
    }

    public function getFaceLockedUntil(): ?\DateTimeInterface
    {
        return $this->faceLockedUntil;
    }

    /** @internal Doctrine / lifecycle only; use recordFaceLockedUntil() from app code. */
    protected function setFaceLockedUntil(?\DateTimeInterface $faceLockedUntil): self
    {
        $this->faceLockedUntil = $faceLockedUntil;
        return $this;
    }

    public function recordFaceLockedUntil(?\DateTimeInterface $until = null): self
    {
        $this->faceLockedUntil = $until;
        return $this;
    }

    public function isFaceLocked(): bool
    {
        if ($this->faceLockedUntil === null) {
            return false;
        }

        $now = new \DateTime();
        return $this->faceLockedUntil > $now;
    }

    public function getFaceEnrolledAt(): ?\DateTimeInterface
    {
        return $this->faceEnrolledAt;
    }

    /** @internal Doctrine / lifecycle only; use recordFaceEnrolledAt() from app code. */
    protected function setFaceEnrolledAt(?\DateTimeInterface $faceEnrolledAt): self
    {
        $this->faceEnrolledAt = $faceEnrolledAt;
        return $this;
    }

    public function recordFaceEnrolledAt(?\DateTimeInterface $when = null): self
    {
        $this->faceEnrolledAt = $when;
        return $this;
    }

    // Login lockout getters/setters

    public function getFailedLoginAttempts(): int
    {
        return $this->failedLoginAttempts;
    }

    public function setFailedLoginAttempts(int $failedLoginAttempts): self
    {
        $this->failedLoginAttempts = $failedLoginAttempts;
        return $this;
    }

    public function getLoginLockedUntil(): ?\DateTimeInterface
    {
        return $this->loginLockedUntil;
    }

    /** @internal Doctrine / lifecycle only. */
    protected function setLoginLockedUntil(?\DateTimeInterface $loginLockedUntil): self
    {
        $this->loginLockedUntil = $loginLockedUntil;
        return $this;
    }

    public function getLastFailedLoginAt(): ?\DateTimeInterface
    {
        return $this->lastFailedLoginAt;
    }

    /** @internal Doctrine / lifecycle only. */
    protected function setLastFailedLoginAt(?\DateTimeInterface $lastFailedLoginAt): self
    {
        $this->lastFailedLoginAt = $lastFailedLoginAt;
        return $this;
    }

    public function isLoginLocked(): bool
    {
        if ($this->loginLockedUntil === null) {
            return false;
        }
        return $this->loginLockedUntil > new \DateTime();
    }

    /** Lock after 3 failed attempts for 10 minutes. */
    public function incrementFailedLoginAttempts(int $lockAfterAttempts = 3, int $lockMinutes = 10): self
    {
        $this->failedLoginAttempts++;
        $this->lastFailedLoginAt = new \DateTime();
        if ($this->failedLoginAttempts >= $lockAfterAttempts) {
            $this->loginLockedUntil = new \DateTime('+' . $lockMinutes . ' minutes');
        }
        return $this;
    }

    public function resetLoginAttempts(): self
    {
        $this->failedLoginAttempts = 0;
        $this->loginLockedUntil = null;
        $this->lastFailedLoginAt = null;
        return $this;
    }

    // Ban getters/setters

    public function isBanned(): bool
    {
        return $this->isBanned;
    }

    public function setBanned(bool $isBanned): self
    {
        $this->isBanned = $isBanned;
        return $this;
    }

    public function getBanReason(): ?string
    {
        return $this->banReason;
    }

    public function setBanReason(?string $banReason): self
    {
        $this->banReason = $banReason;
        return $this;
    }

    public function getBanNote(): ?string
    {
        return $this->banNote;
    }

    public function setBanNote(?string $banNote): self
    {
        $this->banNote = $banNote;
        return $this;
    }

    public function getBannedAt(): ?\DateTimeImmutable
    {
        return $this->bannedAt;
    }

    /** @internal Doctrine / lifecycle only; use recordBannedAt() / clearBanSnapshot() from app code. */
    protected function setBannedAt(?\DateTimeImmutable $bannedAt): self
    {
        $this->bannedAt = $bannedAt;
        return $this;
    }

    public function getBanEndsAt(): ?\DateTimeImmutable
    {
        return $this->banEndsAt;
    }

    /** @internal Doctrine / lifecycle only; use recordBanEndsAt() / clearBanSnapshot() from app code. */
    protected function setBanEndsAt(?\DateTimeImmutable $banEndsAt): self
    {
        $this->banEndsAt = $banEndsAt;
        return $this;
    }

    public function recordBannedAt(?\DateTimeImmutable $when = null): self
    {
        $this->bannedAt = $when ?? new \DateTimeImmutable();
        return $this;
    }

    public function recordBanEndsAt(?\DateTimeImmutable $when): self
    {
        $this->banEndsAt = $when;
        return $this;
    }

    public function clearBanSnapshot(): self
    {
        $this->bannedAt = null;
        $this->banEndsAt = null;
        return $this;
    }

    public function getBannedBy(): ?User
    {
        return $this->bannedBy;
    }

    public function setBannedBy(?User $bannedBy): self
    {
        $this->bannedBy = $bannedBy;
        return $this;
    }

    public function getBanType(): ?string
    {
        return $this->banType;
    }

    public function setBanType(?string $banType): self
    {
        $this->banType = $banType;
        return $this;
    }

    public function getBanCount(): int
    {
        return $this->banCount;
    }

    public function setBanCount(int $banCount): self
    {
        $this->banCount = $banCount;
        return $this;
    }

    // Wallet getters/setters

    public function getAccountBalance(): string
    {
        return $this->accountBalance;
    }

    public function setAccountBalance(string $accountBalance): self
    {
        $this->accountBalance = $accountBalance;
        return $this;
    }

    public function getWalletCurrency(): WalletCurrency
    {
        return WalletCurrency::tryFrom($this->walletCurrency) ?? WalletCurrency::USD;
    }

    public function setWalletCurrency(WalletCurrency|string $walletCurrency): self
    {
        $this->walletCurrency = $walletCurrency instanceof WalletCurrency ? $walletCurrency->value : WalletCurrency::tryFrom($walletCurrency)?->value ?? WalletCurrency::USD->value;
        return $this;
    }

    // Rating getters/setters

    public function getRatingAvg(): ?string
    {
        return $this->ratingAvg;
    }

    public function setRatingAvg(?string $ratingAvg): self
    {
        $this->ratingAvg = $ratingAvg;
        return $this;
    }

    public function getTotalReviews(): ?int
    {
        return $this->totalReviews;
    }

    public function setTotalReviews(?int $totalReviews): self
    {
        $this->totalReviews = $totalReviews;
        return $this;
    }

    // Location getters/setters

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): self
    {
        $this->country = $country;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;
        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): self
    {
        $this->timezone = $timezone;
        return $this;
    }

    // Timestamps getters/setters

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // Certificate getters/setters

    public function getCertificatePath(): ?string
    {
        return $this->certificatePath;
    }

    public function setCertificatePath(?string $certificatePath): self
    {
        $this->certificatePath = $certificatePath;
        return $this;
    }

    public function getCertificateAiScore(): ?int
    {
        return $this->certificateAiScore;
    }

    public function setCertificateAiScore(?int $certificateAiScore): self
    {
        $this->certificateAiScore = $certificateAiScore;
        return $this;
    }

    public function getCertificateAiVerdict(): ?string
    {
        return $this->certificateAiVerdict;
    }

    public function setCertificateAiVerdict(?string $certificateAiVerdict): self
    {
        $this->certificateAiVerdict = $certificateAiVerdict;
        return $this;
    }

    public function getCertificateStatus(): ?CertificateStatus
    {
        return $this->certificateStatus !== null ? CertificateStatus::tryFrom($this->certificateStatus) : null;
    }

    public function setCertificateStatus(CertificateStatus|string|null $certificateStatus): self
    {
        if ($certificateStatus === null) {
            $this->certificateStatus = null;
        } elseif ($certificateStatus instanceof CertificateStatus) {
            $this->certificateStatus = $certificateStatus->value;
        } else {
            $this->certificateStatus = CertificateStatus::tryFrom($certificateStatus)?->value;
        }
        return $this;
    }

    public function getCertificateUploadedAt(): ?\DateTimeInterface
    {
        return $this->certificateUploadedAt;
    }

    /** @internal Doctrine / lifecycle only; use recordCertificateUploadedAt() from app code. */
    protected function setCertificateUploadedAt(?\DateTimeInterface $certificateUploadedAt): self
    {
        $this->certificateUploadedAt = $certificateUploadedAt;
        return $this;
    }

    public function recordCertificateUploadedAt(?\DateTimeInterface $when = null): self
    {
        $this->certificateUploadedAt = $when ?? new \DateTime();
        return $this;
    }

    public function getCertificateApprovedAt(): ?\DateTimeInterface
    {
        return $this->certificateApprovedAt;
    }

    /** @internal Doctrine / lifecycle only; use recordCertificateApprovedAt() from app code. */
    protected function setCertificateApprovedAt(?\DateTimeInterface $certificateApprovedAt): self
    {
        $this->certificateApprovedAt = $certificateApprovedAt;
        return $this;
    }

    public function recordCertificateApprovedAt(?\DateTimeInterface $when = null): self
    {
        $this->certificateApprovedAt = $when ?? new \DateTime();
        return $this;
    }

    public function getCertificateReviewNote(): ?string
    {
        return $this->certificateReviewNote;
    }

    public function setCertificateReviewNote(?string $certificateReviewNote): self
    {
        $this->certificateReviewNote = $certificateReviewNote;
        return $this;
    }

    public function hasCertificate(): bool
    {
        return $this->certificatePath !== null;
    }

    public function isCertificatePending(): bool
    {
        return $this->certificateStatus === CertificateStatus::PENDING->value;
    }

    public function isCertificateApproved(): bool
    {
        return $this->certificateStatus === CertificateStatus::APPROVED->value;
    }

    public function isCertificateRejected(): bool
    {
        return $this->certificateStatus === CertificateStatus::REJECTED->value;
    }

    public function getCertificateExtractedText(): ?string
    {
        return $this->certificateExtractedText;
    }

    public function setCertificateExtractedText(?string $certificateExtractedText): self
    {
        $this->certificateExtractedText = $certificateExtractedText;
        return $this;
    }

    // Email verification getters/setters

    public function getEmailVerificationCode(): ?string
    {
        return $this->emailVerificationCode;
    }

    public function setEmailVerificationCode(?string $emailVerificationCode): self
    {
        $this->emailVerificationCode = $emailVerificationCode;
        return $this;
    }

    public function getEmailVerificationExpiresAt(): ?\DateTimeInterface
    {
        return $this->emailVerificationExpiresAt;
    }

    /** @internal Doctrine / lifecycle only; use setVerificationExpiresAt() from app code. */
    protected function setEmailVerificationExpiresAt(?\DateTimeInterface $emailVerificationExpiresAt): self
    {
        $this->emailVerificationExpiresAt = $emailVerificationExpiresAt;
        return $this;
    }

    public function setVerificationExpiresAt(?\DateTimeInterface $expiresAt): self
    {
        $this->emailVerificationExpiresAt = $expiresAt;
        return $this;
    }

    public function isEmailVerificationCodeValid(): bool
    {
        if (!$this->emailVerificationCode || !$this->emailVerificationExpiresAt) {
            return false;
        }
        return new \DateTime() <= $this->emailVerificationExpiresAt;
    }

    // UserInterface implementation

    /**
     * @see UserInterface
     */
    /**
     * @return string[] Array of ROLE_* strings (e.g. ROLE_ADMIN, ROLE_CLIENT, ROLE_WORKER)
     */
    public function getRoles(): array
    {
        $roles = [];
        $roleEnum = UserRole::tryFrom($this->role);
        if ($roleEnum !== null) {
            $roles[] = $roleEnum->getRole();
        }
        $roles[] = 'ROLE_USER';
        return array_values(array_unique($roles));
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    /**
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->passwordHash;
    }

    public function setPassword(#[\SensitiveParameter] string $password): self
    {
        $this->passwordHash = $password;
        return $this;
    }

    /**
     * @return Collection<int, Ticket>
     */
    public function getTickets(): Collection
    {
        return $this->tickets;
    }

    public function addTicket(Ticket $ticket): static
    {
        if (!$this->tickets->contains($ticket)) {
            $this->tickets->add($ticket);
        }

        return $this;
    }

    public function removeTicket(Ticket $ticket): static
    {
        $this->tickets->removeElement($ticket);

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
            $subTicket->setSender($this);
        }

        return $this;
    }

    public function removeSubTicket(SubTicket $subTicket): static
    {
        if ($this->subTickets->removeElement($subTicket)) {
            // set the owning side to null (unless already changed)
            if ($subTicket->getSender() === $this) {
                $subTicket->setSender(null);
            }
        }

        return $this;
    }
}
