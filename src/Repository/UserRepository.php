<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Clinic;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends AbstractClinicRepository<User>
 */
class UserRepository extends AbstractClinicRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }

    public function findPendingByEmail(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('u.email = :email')
            ->andWhere('u.password IS NULL')
            ->andWhere('u.clinic IS NOT NULL OR u.clinics IS NOT EMPTY')
            ->setParameter('email', $email)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return User[] */
    public function findByClinic(Clinic $clinic): array
    {
        return $this->findBy(['clinic' => $clinic]);
    }

    /** @return User[] */
    public function findAllPendingByEmail(string $email): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.email = :email')
            ->andWhere('u.password IS NULL')
            ->andWhere('u.clinic IS NOT NULL OR u.clinics IS NOT EMPTY')
            ->setParameter('email', $email)
            ->getQuery()
            ->getResult();
    }
}
