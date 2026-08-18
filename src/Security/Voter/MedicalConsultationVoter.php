<?php

namespace App\Security\Voter;

use App\Constant\RoleConstants;
use App\Entity\Animal;
use App\Entity\MedicalConsultation;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class MedicalConsultationVoter extends Voter
{
    public const VIEW = 'CONSULTATION_VIEW';
    public const CREATE = 'CONSULTATION_CREATE';
    public const EDIT = 'CONSULTATION_EDIT';
    public const DELETE = 'CONSULTATION_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if ($attribute === self::CREATE) {
            return $subject === null;
        }

        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)
            && $subject instanceof MedicalConsultation;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $me = $token->getUser();
        if (!$me instanceof User) {
            return false;
        }

        if ($me->isSuperAdmin()) {
            return true;
        }

        return match ($attribute) {
            self::VIEW => $this->canView($subject, $me),
            self::CREATE => $this->canWrite($me),
            self::EDIT, self::DELETE => $this->canWrite($me) && $this->memeClinic($subject, $me),
        };
    }

    // Client : voit ses propres consultations (via l'animal) ; staff : celles de sa clinique
    private function canView(MedicalConsultation $consultation, User $me): bool
    {
        $animal = $consultation->getAnimal();

        if ($me->getRole() === RoleConstants::CLIENT) {
            return $animal ? $this->canViewAnimal($animal, $me) : false;
        }

        return $animal ? $this->canViewAnimal($animal, $me) : $this->memeClinic($consultation, $me);
    }

    private function canViewAnimal(Animal $animal, User $me): bool
    {
        if ($me->getRole() === RoleConstants::CLIENT) {
            return $animal->getProprietaire()?->getUser()?->getId() === $me->getId();
        }

        if ($me->getClinic() === null) {
            return false;
        }

        $owner = $animal->getProprietaire();

        return $owner ? $owner->hasClinic($me->getClinic()) : $this->memeClinic($animal, $me);
    }

    // Staff sauf client ; bénévole seulement dans un établissement refuge/association
    private function canWrite(User $me): bool
    {
        if ($me->getRole() === RoleConstants::CLIENT) {
            return false;
        }

        if ($me->getRole() === RoleConstants::BENEVOLE) {
            return in_array($me->getClinic()?->getType(), ['refuge', 'association'], true);
        }

        return true;
    }

    private function memeClinic(object $entity, User $me): bool
    {
        return $entity->getClinic()?->getId() === $me->getClinic()?->getId();
    }
}
