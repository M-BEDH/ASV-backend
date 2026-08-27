<?php

namespace App\Controller\Api;

use App\Constant\RoleConstants;
use App\Entity\Animal;
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
        if ($me->getRole() === RoleConstants::CLIENT) {
            return $animal->getProprietaire()?->getUser()?->getId() === $me->getId();
        }
        if ($me->getClinic() === null) return false;

        // Refuge/association : un Owner n'est jamais rattaché à la structure,
        // la visibilité staff reste basée sur Animal::getClinic() même après adoption.
        if (\in_array($me->getClinic()->getType(), ['refuge', 'association'], true)) {
        // correction phpStorm :  écrire \in_array(...) avec le backslash en préfixe, 
        // pour dire explicitement à PHP "utilise directement la fonction globale", 
        // ce qui évite la résolution de namespace et permet une petite optimisation.
            return $this->memeClinic($animal);
        }

        $owner = $animal->getProprietaire();
        return $owner ? $owner->hasClinic($me->getClinic()) : $this->memeClinic($animal);
    }

    // Peut créer / modifier / supprimer : tout le staff sauf client et bénévole hors refuge/asso
    protected function canWrite(): bool
    {
        /** @var User $me */
        $me = $this->getUser();

        if ($me->isSuperAdmin()) return true;
        if ($me->getRole() === RoleConstants::CLIENT) return false;
        if ($me->getRole() === RoleConstants::BENEVOLE) {
            return \in_array($me->getClinic()?->getType(), ['refuge', 'association'], true);
        // correction phpStorm :  écrire \in_array(...) avec le backslash en préfixe, idem au dessus
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
