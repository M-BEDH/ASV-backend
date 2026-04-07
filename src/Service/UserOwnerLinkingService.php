<?php

namespace App\Service;

use App\Entity\Clinic;
// use App\Entity\Owner;
use App\Entity\User;
use App\Repository\OwnerRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class UserOwnerLinkingService
{
    public function __construct(
        private OwnerRepository $ownerRepo,
        private UserRepository $userRepo,
    ) {
    }

    // Appelé après l'inscription d'un client : relie le User à un Owner existant
    public function linkUserToOwner(User $user, ?Clinic $clinic, EntityManagerInterface $em): void
    {
        if ($user->getRole() !== 'client' || $clinic === null) {
            return;
        }

        $owner = $this->ownerRepo->findOneBy(['email' => $user->getEmail(), 'clinic' => $clinic]);
        if ($owner !== null && $owner->getUser() === null) {
            $owner->setUser($user);
            $em->flush();
        }
    }

    // Appelé à la création d'un Owner par un véto : relie l'Owner à un User client existant
    // public function linkOwnerToUser(Owner $owner, ?Clinic $clinic): void
    // {
    //     if ($clinic === null || $owner->getEmail() === null) {
    //         return;
    //     }

    //     $user = $this->userRepo->findOneBy(['email' => $owner->getEmail(), 'clinic' => $clinic]);
    //     if ($user !== null && $user->getRole() === 'client') {
    //         $owner->setUser($user);
    //     }
    // }
}
