<?php

namespace App\Repository;

use App\Entity\Animal;
use App\Entity\Clinic;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Animal>
 */
class AnimalRepository extends ServiceEntityRepository
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
    public function findByClinicAccess(Clinic $clinic): array
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
