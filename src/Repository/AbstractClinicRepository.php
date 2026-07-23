<?php

namespace App\Repository;

use App\Entity\Clinic;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * T est une case vide que chaque sous-classe remplit avec son entité via @extends,
 * ex: AnimalRepository déclare "@extends AbstractClinicRepository<Animal>" 
 * Ainsi PHPStan sait que findByClinic() renvoie Animal[] (pas object[] générique).
 * @template T of object
 * @extends ServiceEntityRepository<T>
 */
abstract class AbstractClinicRepository extends ServiceEntityRepository
{
    /** @return T[] */ // return Animal (dans cet exemple)
    abstract public function findByClinic(Clinic $clinic): array;
}
