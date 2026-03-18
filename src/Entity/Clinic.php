<?php

namespace App\Entity;

use App\Repository\ClinicRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ClinicRepository::class)]
#[ORM\Table(name: 'clinics')]
class Clinic
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private ?string $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    private string $type = 'clinique';

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'clinic', targetEntity: User::class)]
    private Collection $users;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
        $this->users = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name ?? $this->id ?? 'Clinic';
    }

    public function getId(): ?string { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    /** @return Collection<int, User> */
    public function getUsers(): Collection { return $this->users; }
}
