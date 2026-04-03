<?php

namespace App\Controller\Api;

use App\Entity\User;

trait ClinicAccessTrait
{
    protected function memeClinic(object $entity): bool
    {
        /** @var User $me */
        $me = $this->getUser();
        return $entity->getClinic()?->getId() === $me->getClinic()?->getId();
    }
}
