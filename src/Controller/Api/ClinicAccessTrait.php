<?php

namespace App\Controller\Api;

use App\Entity\Animal;
use App\Entity\MedicalConsultation;
use App\Entity\User;

trait ClinicAccessTrait
{
    // Pour les entités avec une seule clinique (Animal, MedicalConsultation)
    protected function memeClinic(object $entity): bool
    {
        /** @var User $me */
        $me = $this->getUser();

        if ($me->isSuperAdmin()) {
            return true;
        }

        return $entity->getClinic()?->getId() === $me->getClinic()?->getId();
    }

    // Lecture d'un animal : client toujours autorisé, staff selon clinique de l'owner
    protected function doShowAnimal(Animal $animal): bool
    {
        /** @var User $me */
        $me = $this->getUser();
        if ($me->isSuperAdmin()) return true;
        if ($me->getRole() === 'client') {
            return $animal->getProprietaire()?->getUser()?->getId() === $me->getId();
        }
        if ($me->getClinic() === null) return false;
        $owner = $animal->getProprietaire();
        return $owner ? $owner->hasClinic($me->getClinic()) : $this->memeClinic($animal);
    }

    // Lecture d'une consultation : client toujours autorisé, délègue à la visibilité de l'animal lié
    protected function doShowConsultation(MedicalConsultation $consultation): bool
    {
        /** @var User $me */
        $me = $this->getUser();
        if ($me->isSuperAdmin()) return true;
        if ($me->getRole() === 'client') {
            $animal = $consultation->getAnimal();
            return $animal ? $this->doShowAnimal($animal) : false;
        }
        $animal = $consultation->getAnimal();
        return $animal ? $this->doShowAnimal($animal) : $this->memeClinic($consultation);
    }

    // Pour les entités multi-cliniques (Owner)
    protected function aUneClinicCommune(object $entity): bool
    {
        /** @var User $me */
        $me = $this->getUser();

        if ($me->isSuperAdmin()) {
            return true;
        }

        if ($me->getClinic() === null) {
            return false;
        }

        return $entity->hasClinic($me->getClinic());
    }
}
