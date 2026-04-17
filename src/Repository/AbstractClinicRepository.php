<?php

namespace App\Repository;

use App\Entity\Clinic;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

abstract class AbstractClinicRepository extends ServiceEntityRepository
{
    /** @return array<object> */
    abstract public function findByClinic(Clinic $clinic): array;
}
