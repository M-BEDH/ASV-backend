<?php

namespace App\Security;

use App\Constant\RoleConstants;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class AdminUserProvider implements UserProviderInterface
{
    public function __construct(private UserRepository $userRepo) {}

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->userRepo->findOneBy([
            'email' => $identifier,
            'clinic' => null,
        ]);

        if (!$user || $user->getRole() !== RoleConstants::SUPER_ADMIN) {
            throw new UserNotFoundException(sprintf('Super admin "%s" introuvable.', $identifier));
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new \InvalidArgumentException(sprintf('Classe utilisateur invalide "%s".', get_class($user)));
        }
        return $this->loadUserByIdentifier($user->getEmail());
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }
}
