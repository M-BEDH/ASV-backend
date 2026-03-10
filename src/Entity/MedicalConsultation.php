<?php

namespace App\Entity;

use App\Repository\MedicalConsultationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: MedicalConsultationRepository::class)]
#[ORM\Table(name: 'medical_consultations')]
class MedicalConsultation
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Animal::class, inversedBy: 'medicalConsultations')]
    #[ORM\JoinColumn(name: 'animal_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Animal $animal = null;

    #[ORM\Column(name: 'date_consultation', type: 'datetime')]
    private ?\DateTimeInterface $dateConsultation = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'medicalConsultationsAsVeterinaire')]
    #[ORM\JoinColumn(name: 'veterinaire_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $veterinaire = null;

    #[ORM\ManyToOne(targetEntity: Clinic::class)]
    #[ORM\JoinColumn(name: 'clinic_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Clinic $clinic = null;

    #[ORM\Column(type: 'text')]
    private ?string $motif = null;

    #[ORM\Column(name: 'compte_rendu', type: 'text', nullable: true)]
    private ?string $compteRendu = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $traitements = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return ($this->dateConsultation?->format('Y-m-d H:i')) ?? ($this->id ?? 'Consultation');
    }

    public function getId(): ?string { return $this->id; }
    public function setId(string $id): static { $this->id = $id; return $this; }

    public function getAnimal(): ?Animal { return $this->animal; }
    public function setAnimal(?Animal $animal): static { $this->animal = $animal; return $this; }

    public function getDateConsultation(): ?\DateTimeInterface { return $this->dateConsultation; }
    public function setDateConsultation(\DateTimeInterface $dateConsultation): static { $this->dateConsultation = $dateConsultation; return $this; }

    public function getVeterinaire(): ?User { return $this->veterinaire; }
    public function setVeterinaire(?User $veterinaire): static { $this->veterinaire = $veterinaire; return $this; }

    public function getClinic(): ?Clinic { return $this->clinic; }
    public function setClinic(?Clinic $clinic): static { $this->clinic = $clinic; return $this; }

    public function getMotif(): ?string { return $this->motif; }
    public function setMotif(string $motif): static { $this->motif = $motif; return $this; }

    public function getCompteRendu(): ?string { return $this->compteRendu; }
    public function setCompteRendu(?string $compteRendu): static { $this->compteRendu = $compteRendu; return $this; }

    public function getTraitements(): ?string { return $this->traitements; }
    public function setTraitements(?string $traitements): static { $this->traitements = $traitements; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
}