<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private ?string $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 20)]
    private ?string $role = null;

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

    public function getId(): ?string { return $this->id; }
    public function setId(string $id): static { $this->id = $id; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getRole(): ?string { return $this->role; }
    public function setRole(string $role): static { $this->role = $role; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    /** @return Collection<int, Animal> */
    public function getCreatedAnimals(): Collection { return $this->createdAnimals; }

    /** @return Collection<int, Owner> */
    public function getCreatedOwners(): Collection { return $this->createdOwners; }

    /** @return Collection<int, Owner> */
    public function getLinkedOwners(): Collection { return $this->linkedOwners; }

    /** @return Collection<int, MedicalConsultation> */
    public function getMedicalConsultationsAsVeterinaire(): Collection { return $this->medicalConsultationsAsVeterinaire; }
}