<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

use App\Repository\UserRepository;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(type: 'string', length: 25)]
    private ?string $role = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $password = null;

    #[ORM\Column(type: 'string', length: 25)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 25)]
    #[Assert\NotBlank]
    private ?string $last_name = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: 'string', length: 25, unique: true)]
    private ?string $username = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(type: 'boolean', nullable: true, options: ['default' => 0])]
    private ?bool $two_factor_enabled = false;

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    private ?string $two_factor_code = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $two_factor_expiry = null;

    #[ORM\Column(type: 'blob', nullable: true)]
    private $face_data = null;

    #[ORM\Column(type: 'blob', nullable: true)]
    private $fingerprint_data = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $fingerprint_slot_id = null;

    public function __construct()
    {
        $this->date = new \DateTime();
        $this->role = 'USER';
    }

    public function getRoles(): array
    {
        $storedRole = strtoupper((string) ($this->role ?? 'USER'));
        $roleMap = [
            'USER' => 'ROLE_USER',
            'GUIDER' => 'ROLE_GUIDE',
            'AGENCY' => 'ROLE_AGENCY',
            'ADMIN' => 'ROLE_ADMIN',
            'ROLE_USER' => 'ROLE_USER',
            'ROLE_GUIDE' => 'ROLE_GUIDE',
            'ROLE_AGENCY' => 'ROLE_AGENCY',
            'ROLE_ADMIN' => 'ROLE_ADMIN',
        ];
        $primaryRole = $roleMap[$storedRole] ?? 'ROLE_USER';
        return array_values(array_unique([$primaryRole, 'ROLE_USER']));
    }

    public function eraseCredentials(): void
    {
        $this->two_factor_code = null;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getId(): ?int { return $this->id; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getRole(): ?string { return $this->role; }
    public function setRole(?string $role): static {
        $normalized = strtoupper((string) $role);
        $dbRoleMap = [
            'ROLE_USER' => 'USER', 'ROLE_GUIDE' => 'GUIDER', 'ROLE_AGENCY' => 'AGENCY', 'ROLE_ADMIN' => 'ADMIN',
            'USER' => 'USER', 'GUIDER' => 'GUIDER', 'AGENCY' => 'AGENCY', 'ADMIN' => 'ADMIN',
        ];
        $this->role = $dbRoleMap[$normalized] ?? 'USER';
        return $this;
    }

    public function setRoles(array $roles): static {
        $this->setRole(!empty($roles) ? $roles[0] : 'USER');
        return $this;
    }

    public function getPassword(): ?string { return $this->password; }
    public function setPassword(string $password): static { $this->password = $password; return $this; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getLastName(): ?string { return $this->last_name; }
    public function setLastName(string $last_name): static { $this->last_name = $last_name; return $this; }

    public function getFullName(): string { return trim($this->name . ' ' . $this->last_name); }

    public function getDate(): ?\DateTimeInterface { return $this->date; }
    public function setDate(\DateTimeInterface $date): static { $this->date = $date; return $this; }

    public function getUsername(): ?string { return $this->username; }
    public function setUsername(string $username): static { $this->username = $username; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(?string $status): static { $this->status = $status; return $this; }

    public function isTwoFactorEnabled(): ?bool { return $this->two_factor_enabled; }
    public function setTwoFactorEnabled(?bool $two_factor_enabled): static { $this->two_factor_enabled = $two_factor_enabled; return $this; }

    public function getTwoFactorCode(): ?string { return $this->two_factor_code; }
    public function setTwoFactorCode(?string $two_factor_code): static { $this->two_factor_code = $two_factor_code; return $this; }

    public function getTwoFactorExpiry(): ?\DateTimeInterface { return $this->two_factor_expiry; }
    public function setTwoFactorExpiry(?\DateTimeInterface $two_factor_expiry): static { $this->two_factor_expiry = $two_factor_expiry; return $this; }

    public function getFaceData() { return $this->face_data; }
    public function setFaceData($face_data): static { $this->face_data = $face_data; return $this; }

    public function getFingerprintData() { return $this->fingerprint_data; }
    public function setFingerprintData($fingerprint_data): static { $this->fingerprint_data = $fingerprint_data; return $this; }

    public function getFingerprintSlotId(): ?int { return $this->fingerprint_slot_id; }
    public function setFingerprintSlotId(?int $fingerprint_slot_id): static { $this->fingerprint_slot_id = $fingerprint_slot_id; return $this; }

    public function getFirstName(): ?string { return $this->name; }
    
    public function getPhone(): ?string { return null; }
    public function getAddress(): ?string { return null; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->date; }

    public function __toString(): string { return $this->getFullName() ?: $this->email ?? 'New User'; }
}
