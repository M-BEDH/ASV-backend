<?php

namespace App\Repository;

use App\Entity\Clinic;
use App\Entity\Owner;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Owner>
 */
class OwnerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Owner::class);
    }

    //    /**
    //     * @return Owner[] Returns an array of Owner objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('o.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    /** @return Owner[] */
    public function findByClinic(Clinic $clinic): array
    {
        return $this->createQueryBuilder('o')
            ->innerJoin('o.clinics', 'c')
            ->andWhere('c.id = :clinicId')
            ->setParameter('clinicId', $clinic->getId())
            ->orderBy('o.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByEmailAndClinic(string $email, Clinic $clinic): ?Owner
    {
        return $this->createQueryBuilder('o')
            ->innerJoin('o.clinics', 'c')
            ->andWhere('o.email = :email')
            ->andWhere('c.id = :clinicId')
            ->setParameter('email', $email)
            ->setParameter('clinicId', $clinic->getId())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
