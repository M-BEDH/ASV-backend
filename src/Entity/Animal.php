<?php

namespace App\Entity;

use App\Repository\AnimalRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AnimalRepository::class)]
#[ORM\Table(name: 'animals')]
class Animal
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private ?string $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $espece = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $race = null;

    #[ORM\Column(name: 'date_naissance', type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateNaissance = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $remarques = null;

    #[ORM\ManyToOne(targetEntity: Owner::class, inversedBy: 'animals')]
    #[ORM\JoinColumn(name: 'proprietaire_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Owner $proprietaire = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'createdAnimals')]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: Clinic::class)]
    #[ORM\JoinColumn(name: 'clinic_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Clinic $clinic = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'animal', targetEntity: MedicalConsultation::class)]
    private Collection $medicalConsultations;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
        $this->medicalConsultations = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->nom ?? $this->id ?? 'Animal';
    }

    public function getId(): ?string { return $this->id; }
    public function setId(string $id): static { $this->id = $id; return $this; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }

    public function getEspece(): ?string { return $this->espece; }
    public function setEspece(string $espece): static { $this->espece = $espece; return $this; }

    public function getRace(): ?string { return $this->race; }
    public function setRace(?string $race): static { $this->race = $race; return $this; }

    public function getDateNaissance(): ?\DateTimeInterface { return $this->dateNaissance; }
    public function setDateNaissance(?\DateTimeInterface $dateNaissance): static { $this->dateNaissance = $dateNaissance; return $this; }

    public function getRemarques(): ?string { return $this->remarques; }
    public function setRemarques(?string $remarques): static { $this->remarques = $remarques; return $this; }

    public function getProprietaire(): ?Owner { return $this->proprietaire; }
    public function setProprietaire(?Owner $proprietaire): static { $this->proprietaire = $proprietaire; return $this; }

    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function setCreatedBy(?User $createdBy): static { $this->createdBy = $createdBy; return $this; }

    public function getClinic(): ?Clinic { return $this->clinic; }
    public function setClinic(?Clinic $clinic): static { $this->clinic = $clinic; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    /** @return Collection<int, MedicalConsultation> */
    public function getMedicalConsultations(): Collection { return $this->medicalConsultations; }

    public function addMedicalConsultation(MedicalConsultation $medicalConsultation): static
    {
        if (!$this->medicalConsultations->contains($medicalConsultation)) {
            $this->medicalConsultations->add($medicalConsultation);
            $medicalConsultation->setAnimal($this);
        }
        return $this;
    }

    public function removeMedicalConsultation(MedicalConsultation $medicalConsultation): static
    {
        if ($this->medicalConsultations->removeElement($medicalConsultation)) {
            if ($medicalConsultation->getAnimal() === $this) {
                $medicalConsultation->setAnimal(null);
            }
        }
        return $this;
    }
}