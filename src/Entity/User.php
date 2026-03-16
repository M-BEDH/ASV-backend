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
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private ?string $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\Email(message: "L'adresse email '{{ value }}' n'est pas valide.")]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 20)]
    private ?string $role = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\ManyToOne(targetEntity: Clinic::class, inversedBy: 'users')]
    #[ORM\JoinColumn(name: 'clinic_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Clinic $clinic = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'createdBy', targetEntity: Animal::class)]
    private Collection $createdAnimals;

    #[ORM\OneToMany(mappedBy: 'createdBy', targetEntity: Owner::class)]
    private Collection $createdOwners;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Owner::class)]
    private Collection $linkedOwners;

    #[ORM\OneToMany(mappedBy: 'veterinaire', targetEntity: MedicalConsultation::class)]
    private Collection $medicalConsultationsAsVeterinaire;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
        $this->createdAnimals = new ArrayCollection();
        $this->createdOwners = new ArrayCollection();
        $this->linkedOwners = new ArrayCollection();
        $this->medicalConsultationsAsVeterinaire = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name ?? $this->email ?? ($this->id ?? 'User');
    }

    // --- UserInterface ---

    public function getUserIdentifier(): string
    {
        return $this->email ?? '';
    }

    public function getRoles(): array
    {
        // assistant has same rights as veterinaire
        $symfonyRole = match ($this->role) {
            'veterinaire', 'assistant' => 'ROLE_VETERINAIRE',
            'client'                   => 'ROLE_CLIENT',
            default                    => 'ROLE_USER',
        };

        return [$symfonyRole, 'ROLE_USER'];
    }

    public function eraseCredentials(): void {}

    // --- PasswordAuthenticatedUserInterface ---

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    // --- Getters / Setters ---

    public function getId(): ?string { return $this->id; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getRole(): ?string { return $this->role; }
    public function setRole(string $role): static { $this->role = $role; return $this; }

    public function getClinic(): ?Clinic { return $this->clinic; }
    public function setClinic(?Clinic $clinic): static { $this->clinic = $clinic; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    /** @return Collection<int, Animal> */
    public function getCreatedAnimals(): Collection { return $this->createdAnimals; }

    /** @return Collection<int, Owner> */
    public function getCreatedOwners(): Collection { return $this->createdOwners; }

    /** @return Collection<int, Owner> */
    public function getLinkedOwners(): Collection { return $this->linkedOwners; }

    /** @return Collection<int, MedicalConsultation> */
    public function getMedicalConsultationsAsVeterinaire(): Collection { return $this->medicalConsultationsAsVeterinaire; }
}
