<?php

namespace App\Service;

use App\Entity\Clinic;
use App\Entity\Owner;
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

    // Appelé après l'inscription d'un client : relie le User à tous ses Owner existants
    public function linkUserToOwner(User $user, ?Clinic $clinic, EntityManagerInterface $em): void
    {
        if ($user->getRole() !== 'client') {
            return;
        }

        $owners = $this->ownerRepo->findBy(['email' => $user->getEmail()]);
        foreach ($owners as $owner) {
            if ($owner->getUser() === null) {
                $owner->setUser($user);
            }
            // Ajoute toutes les cliniques de l'owner au user client
            foreach ($owner->getClinics() as $ownerClinic) {
                $user->addClinic($ownerClinic);
            }
        }

        if (!empty($owners)) {
            $em->flush();
        }
    }

 }
