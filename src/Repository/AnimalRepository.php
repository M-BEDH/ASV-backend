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

    //    /**
    //     * @return Animal[] Returns an array of Animal objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('a.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Animal
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
