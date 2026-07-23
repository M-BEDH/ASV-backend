<?php

namespace App\Command;

use App\Constant\RoleConstants;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-super-admin',
    description: 'Crée le compte super administrateur de la plateforme.',
)]
class CreateSuperAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepo,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Création du Super Administrateur ASV');

        // Vérifie si un super admin existe déjà
        $existing = $this->userRepo->findOneBy(['role' => 'super_admin', 'clinic' => null]);
        if ($existing) {
            $io->error('Un super administrateur existe déjà. Création impossible. Rapprochez-vous de l\'administrateur du site.');
            return Command::FAILURE;
        }

        $email = $io->ask('Email', null, function (?string $value): string {
            if (empty($value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Email invalide.');
            }
            return $value;
        });

        $name = $io->ask('Nom complet', null, function (?string $value): string {
            if (empty(trim($value ?? ''))) {
                throw new \RuntimeException('Le nom ne peut pas être vide.');
            }
            return trim($value);
        });

        $password = $io->askHidden('Mot de passe', function (?string $value): string {
            if (empty($value) || strlen($value) < 8) {
                throw new \RuntimeException('Le mot de passe doit faire au moins 8 caractères.');
            }
            return $value;
        });

        $user = new User();
        $user->setEmail($email);
        $user->setName($name);
        $user->setRole(RoleConstants::SUPER_ADMIN);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        // clinic = null : le super admin n'appartient à aucun établissement

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('Super admin créé avec succès : %s (%s)', $name, $email));
        $io->note('Connectez-vous sur http://localhost:8080/admin');

        return Command::SUCCESS;
    }
}
