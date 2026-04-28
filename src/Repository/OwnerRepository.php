<?php

namespace App\Repository;

use App\Entity\Clinic;
use App\Entity\Owner;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AbstractClinicRepository<Owner>
 */
class OwnerRepository extends AbstractClinicRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Owner::class);
    }

  
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
