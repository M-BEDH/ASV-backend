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

    // Un client ne voit que ses propres animaux. Le staff voit les animaux dont le propriétaire est rattaché à leur clinique
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

    // Peut créer / modifier / supprimer : tout le staff sauf client et bénévole hors refuge/asso
    protected function canWrite(): bool
    {
        /** @var User $me */
        $me = $this->getUser();

        if ($me->isSuperAdmin()) return true;
        if ($me->getRole() === 'client') return false;
        if ($me->getRole() === 'benevole') {
            return in_array($me->getClinic()?->getType(), ['refuge', 'association'], true);
        }
        return true;
    }

    // Vérifie que l'entité partage au moins une clinique avec l'utilisateur connecté (Owner multi-cliniques)
    protected function hasSharedClinic(object $entity): bool
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
