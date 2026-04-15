<?php

namespace App\Repository;

use App\Entity\Clinic;
use App\Entity\MedicalConsultation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MedicalConsultation>
 */
class MedicalConsultationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MedicalConsultation::class);
    }

    /**
     * Récupère les consultations d'un animal avec le vétérinaire en une seule requête (évite le N+1).
     *
     * @return MedicalConsultation[]
     */
    public function findByAnimalWithVet(string $animalId): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.veterinaire', 'v')
            ->addSelect('v')
            ->where('c.animal = :animalId')
            ->setParameter('animalId', $animalId)
            ->orderBy('c.dateConsultation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère toutes les consultations d'une clinique avec animal + vétérinaire en une seule requête.
     *
     * @return MedicalConsultation[]
     */
    public function findByClinicWithRelations(?string $clinicId): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.animal', 'a')
            ->addSelect('a')
            ->leftJoin('c.veterinaire', 'v')
            ->addSelect('v')
            ->orderBy('c.dateConsultation', 'DESC');

        if ($clinicId === null) {
            $qb->where('c.clinic IS NULL');
        } else {
            $qb->where('c.clinic = :clinicId')->setParameter('clinicId', $clinicId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Consultations visibles par une clinique :
        * - consultations rattachées à cette clinique
        * - animaux dont l'owner est lié à cette clinique
     *
     * @return MedicalConsultation[]
     */
    public function findByClinicAccess(Clinic $clinic): array
    {
        return $this->createQueryBuilder('c')
            ->distinct()
            ->leftJoin('c.animal', 'a')
            ->addSelect('a')
            ->leftJoin('c.veterinaire', 'v')
            ->addSelect('v')
            ->leftJoin('a.proprietaire', 'o')
            ->leftJoin('o.clinics', 'oc')
            ->where('c.clinic = :clinic')
            ->orWhere('oc = :clinic')
            ->setParameter('clinic', $clinic)
            ->orderBy('c.dateConsultation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les consultations des animaux d'un propriétaire avec les relations.
     *
     * @return MedicalConsultation[]
     */
    public function findByOwnerWithRelations(string $ownerId): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.animal', 'a')
            ->addSelect('a')
            ->leftJoin('c.veterinaire', 'v')
            ->addSelect('v')
            ->leftJoin('a.proprietaire', 'o')
            ->where('o.id = :ownerId')
            ->setParameter('ownerId', $ownerId)
            ->orderBy('c.dateConsultation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param string[] $ownerIds
     * @return MedicalConsultation[]
     */
    public function findByOwnersWithRelations(array $ownerIds): array
    {
        if ($ownerIds === []) {
            return [];
        }

        return $this->createQueryBuilder('c')
            ->distinct()
            ->leftJoin('c.animal', 'a')
            ->addSelect('a')
            ->leftJoin('c.veterinaire', 'v')
            ->addSelect('v')
            ->leftJoin('a.proprietaire', 'o')
            ->where('o.id IN (:ownerIds)')
            ->setParameter('ownerIds', $ownerIds)
            ->orderBy('c.dateConsultation', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
