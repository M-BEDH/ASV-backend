<?php

namespace App\Repository;

use App\Entity\Animal;
use App\Entity\Clinic;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AbstractClinicRepository<Animal>
 */
class AnimalRepository extends AbstractClinicRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Animal::class);
    }

    /**
     * Retourne tous les animaux visibles par une clinique :
     * - animaux dont l'owner est lié à cette clinique
     * - animaux sans owner mais créés par cette clinique
     *
     * @return Animal[]
     */
    public function findByClinic(Clinic $clinic): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.proprietaire', 'o')
            ->leftJoin('o.clinics', 'c')
            ->where('c = :clinic')
            ->orWhere('a.proprietaire IS NULL AND a.clinic = :clinic')
            ->setParameter('clinic', $clinic)
            ->getQuery()
            ->getResult();
    }

    /** Retourne tous les animaux appartenant aux propriétaires spécifiés
     * @param string[] $ownerIds
     * @return Animal[]
     */
    public function findByOwnerIds(array $ownerIds): array
    {
        if ($ownerIds === []) {
            return [];
        }

        return $this->createQueryBuilder('a')
            ->leftJoin('a.proprietaire', 'o')
            ->addSelect('o')
            ->where('o.id IN (:ownerIds)')
            ->setParameter('ownerIds', $ownerIds)
            ->getQuery()
            ->getResult();
    }

}
