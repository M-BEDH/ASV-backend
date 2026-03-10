<?php

namespace App\Controller\Api;

use App\Entity\Clinic;
use App\Entity\User;
use App\Repository\ClinicRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
final class AuthController extends AbstractController
{
    #[Route('/register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        UserRepository $userRepo,
        ClinicRepository $clinicRepo,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (empty($data['email']) || empty($data['password']) || empty($data['name']) || empty($data['role'])) {
            return $this->json(['error' => 'Les champs email, password, name et role sont obligatoires.'], 400);
        }

        $allowedRoles = ['client', 'veterinaire', 'assistant'];
        if (!in_array($data['role'], $allowedRoles, true)) {
            return $this->json(['error' => 'Rôle invalide. Valeurs acceptées : client, veterinaire, assistant.'], 400);
        }

        if ($userRepo->findOneBy(['email' => $data['email']])) {
            return $this->json(['error' => 'Cet email est déjà utilisé.'], 409);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setName($data['name']);
        $user->setRole($data['role']);
        $user->setPassword($hasher->hashPassword($user, $data['password']));

        // Clinic assignment
        $clinic = null;
        if ($data['role'] === 'veterinaire') {
            if (!empty($data['clinicName'])) {
                // Create a new clinic
                $clinic = new Clinic();
                $clinic->setName($data['clinicName']);
                $em->persist($clinic);
            } elseif (!empty($data['clinicId'])) {
                // Join an existing clinic
                $clinic = $clinicRepo->find($data['clinicId']);
                if (!$clinic) {
                    return $this->json(['error' => 'Clinique introuvable.'], 404);
                }
            }
            // No clinic provided: allowed (vet can add it later)
        } elseif ($data['role'] === 'assistant') {
            if (empty($data['clinicId'])) {
                return $this->json(['error' => 'Un assistant doit rejoindre une clinique existante (clinicId requis).'], 400);
            }
            $clinic = $clinicRepo->find($data['clinicId']);
            if (!$clinic) {
                return $this->json(['error' => 'Clinique introuvable.'], 404);
            }
        } elseif ($data['role'] === 'client' && !empty($data['clinicId'])) {
            $clinic = $clinicRepo->find($data['clinicId']);
            if (!$clinic) {
                return $this->json(['error' => 'Clinique introuvable.'], 404);
            }
        }

        $user->setClinic($clinic);
        $em->persist($user);
        $em->flush();

        return $this->json([
            'id'       => $user->getId(),
            'email'    => $user->getEmail(),
            'name'     => $user->getName(),
            'role'     => $user->getRole(),
            'clinicId' => $user->getClinic()?->getId(),
        ], 201);
    }

    #[Route('/login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $userRepo,
        UserPasswordHasherInterface $hasher,
        JWTTokenManagerInterface $jwtManager,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (empty($data['email']) || empty($data['password'])) {
            return $this->json(['error' => 'Email et password requis.'], 400);
        }

        $user = $userRepo->findOneBy(['email' => $data['email']]);

        if (!$user || !$hasher->isPasswordValid($user, $data['password'])) {
            return $this->json(['error' => 'Identifiants invalides.'], 401);
        }

        return $this->json([
            'token' => $jwtManager->create($user),
            'user'  => [
                'id'       => $user->getId(),
                'email'    => $user->getEmail(),
                'name'     => $user->getName(),
                'role'     => $user->getRole(),
                'clinicId' => $user->getClinic()?->getId(),
            ],
        ]);
    }

    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'id'       => $user->getId(),
            'email'    => $user->getEmail(),
            'name'     => $user->getName(),
            'role'     => $user->getRole(),
            'clinicId' => $user->getClinic()?->getId(),
        ]);
    }
}
