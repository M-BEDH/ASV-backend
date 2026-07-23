<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_user_email_clinic', columns: ['email', 'clinic_id'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private ?string $id = null; // @phpstan-ignore doctrine.columnType

    #[ORM\Column(length: 255)]
    #[Assert\Email(message: "L'adresse email '{{ value }}' n'est pas valide.")]
    private ?string $email = null; // @phpstan-ignore doctrine.columnType

    #[ORM\Column(length: 255)]
    private ?string $name = null; // @phpstan-ignore doctrine.columnType

    #[ORM\Column(length: 20)]
    private ?string $role = null; // @phpstan-ignore doctrine.columnType

    #[ORM\Column(name: 'is_vet', type: 'boolean', options: ['default' => false])]
    private bool $isVet = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\ManyToOne(targetEntity: Clinic::class, inversedBy: 'users')]
    #[ORM\JoinColumn(name: 'clinic_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Clinic $clinic = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null; // @phpstan-ignore doctrine.columnType

    /** @var Collection<int, Animal> */
    #[ORM\OneToMany(mappedBy: 'createdBy', targetEntity: Animal::class)]
    private Collection $createdAnimals;

    /** @var Collection<int, Owner> */
    #[ORM\OneToMany(mappedBy: 'createdBy', targetEntity: Owner::class)]
    private Collection $createdOwners;

    /** @var Collection<int, Owner> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Owner::class)]
    private Collection $linkedOwners;

    /** @var Collection<int, MedicalConsultation> */
    #[ORM\OneToMany(mappedBy: 'veterinaire', targetEntity: MedicalConsultation::class)]
    private Collection $medicalConsultationsAsVeterinaire;

    // Uniquement pour les clients : cliniques où ils sont propriétaires
    /** @var Collection<int, Clinic> */
    #[ORM\ManyToMany(targetEntity: Clinic::class)]
    #[ORM\JoinTable(name: 'user_clinic')]
    private Collection $clinics;

    // Champs transitoires — utilisés uniquement lors de la création d'un responsable via EasyAdmin (non persistés)
    private ?string $newClinicName = null;
    private ?string $newClinicType = null;

    public function __construct()
    {
        // Génère un UUID v4 au format RFC 4122
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
        $this->createdAnimals = new ArrayCollection();
        $this->createdOwners = new ArrayCollection();
        $this->linkedOwners = new ArrayCollection();
        $this->medicalConsultationsAsVeterinaire = new ArrayCollection();
        $this->clinics = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name ?? $this->email ?? ($this->id ?? 'User');
    }

    // --- UserInterface ---

    public function getUserIdentifier(): string
    {
        return $this->id ?? '';
    }

    public function getRoles(): array
    {
        $symfonyRole = match ($this->role) {
            'super_admin'              => 'ROLE_SUPER_ADMIN',
            'veterinaire', 'assistant', 'responsable' => 'ROLE_VETERINAIRE',
            'client'                   => 'ROLE_CLIENT',
            default                    => 'ROLE_USER',
        };

        return [$symfonyRole, 'ROLE_USER'];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isVet(): bool
    {
        return $this->isVet;
    }

    public function eraseCredentials(): void
    {
    }

    // --- PasswordAuthenticatedUserInterface ---

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;
        return $this;
    }

    // --- Getters / Setters ---

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }
    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
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

    public function getRole(): ?string
    {
        return $this->role;
    }
    public function setRole(string $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function setIsVet(bool $isVet): static
    {
        $this->isVet = $isVet;
        return $this;
    }

    public function getClinic(): ?Clinic
    {
        return $this->clinic;
    }
    public function setClinic(?Clinic $clinic): static
    {
        $this->clinic = $clinic;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Animal> */
    public function getCreatedAnimals(): Collection
    {
        return $this->createdAnimals;
    }

    /** @return Collection<int, Owner> */
    public function getCreatedOwners(): Collection
    {
        return $this->createdOwners;
    }

    /** @return Collection<int, Owner> */
    public function getLinkedOwners(): Collection
    {
        return $this->linkedOwners;
    }

    /** @return Collection<int, MedicalConsultation> */
    public function getMedicalConsultationsAsVeterinaire(): Collection
    {
        return $this->medicalConsultationsAsVeterinaire;
    }

    /** @return Collection<int, Clinic> */
    public function getClinics(): Collection
    {
        return $this->clinics;
    }

    public function addClinic(Clinic $clinic): static
    {
        if (!$this->clinics->contains($clinic)) {
            $this->clinics->add($clinic);
        }
        return $this;
    }

    public function removeClinic(Clinic $clinic): static
    {
        $this->clinics->removeElement($clinic);
        return $this;
    }

    public function hasClinic(Clinic $clinic): bool
    {
        return $this->clinics->contains($clinic);
    }

    public function getNewClinicName(): ?string { return $this->newClinicName; }
    public function setNewClinicName(?string $v): static { $this->newClinicName = $v; return $this; }

    public function getNewClinicType(): ?string { return $this->newClinicType; }
    public function setNewClinicType(?string $v): static { $this->newClinicType = $v; return $this; }

    // Anonymise les données d'un utilisateur (lors de la suppression d'un compte client)
    public function anonymize(): static
    {
        $this->name     = 'Utilisateur supprimé';
        // On génère un email unique pour éviter les conflits d'unicité avec d'autres comptes supprimés
        $this->email    = 'supprime_' . $this->id . '@deleted.local';
        $this->password = bin2hex(random_bytes(32));
        $this->clinic   = null;
        $this->clinics->clear();
        return $this;
    }
}
