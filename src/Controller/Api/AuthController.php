<?php

namespace App\Controller\Api;

use App\Entity\Clinic;
use App\Entity\User;
use App\Repository\ClinicRepository;
use App\Repository\UserRepository;
use App\Constant\RoleConstants;
use App\Service\ApiValidator;
use App\Service\SerializerService;
use App\Service\UserOwnerLinkingService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Prometheus\CollectorRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
final class AuthController extends AbstractController
{
    public function __construct(
        private CollectorRegistry $prometheusRegistry,
    ) {
    }

    #[Route('/register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        UserRepository $userRepo,
        ClinicRepository $clinicRepo,
        ApiValidator $validator,
        UserOwnerLinkingService $linking,
        SerializerService $serializer,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if ($error = $validator->validateUserCreate($data)) {
            return $this->json(['error' => $error], 400);
        }

        if (!in_array($data['role'], RoleConstants::ALL, true)) {
            return $this->json(['error' => 'Rôle invalide. Valeurs acceptées : client, veterinaire, responsable, assistant, benevole.'], 400);
        }

        // Unicité email par établissement (un client peut s'inscrire dans plusieurs établissements)
        // On vérifie après avoir résolu la clinique — voir vérification en fin de bloc ci-dessous

        $user = new User();
        $user->setEmail($data['email']);
        $user->setName($data['name']);
        $user->setRole($data['role']);
        $user->setPassword($hasher->hashPassword($user, $data['password']));

        // Établissement assignment
        $allowedTypes = ['clinique', 'refuge', 'association'];
        $clinic = null;
        if ($data['role'] === 'veterinaire' || $data['role'] === 'responsable') {
            if (!empty($data['clinicName'])) {
                  // crée un nouvel établissement
                $clinic = new Clinic();
                $clinic->setName($data['clinicName']);
                if (!empty($data['clinicType']) && in_array($data['clinicType'], $allowedTypes, true)) {
                    $clinic->setType($data['clinicType']);
                }
                $em->persist($clinic);
            } elseif (!empty($data['clinicId'])) {
                   // rejoint un établissement existant
                $clinic = $clinicRepo->find($data['clinicId']);
                if (!$clinic) {
                    return $this->json(['error' => 'Établissement introuvable.'], 404);
                }
            } else {
                return $this->json(['error' => 'Un vétérinaire ou responsable doit créer ou rejoindre un établissement.'], 400);
            }
        } elseif ($data['role'] === 'assistant' || $data['role'] === 'benevole') {
            if (empty($data['clinicId'])) {
                return $this->json(['error' => 'Un assistant ou bénévole doit rejoindre un établissement existant (clinicId requis).'], 400);
            }
            $clinic = $clinicRepo->find($data['clinicId']);
            if (!$clinic) {
                return $this->json(['error' => 'Établissement introuvable.'], 404);
            }
        } elseif ($data['role'] === 'client') {
            if (empty($data['clinicId'])) {
                return $this->json(['error' => 'Un client doit choisir un établissement (clinicId requis).'], 400);
            }
            $clinic = $clinicRepo->find($data['clinicId']);
            if (!$clinic) {
                return $this->json(['error' => 'Établissement introuvable.'], 404);
            }
        }

        // Vérifie unicité email dans cet établissement
        if ($userRepo->findOneBy(['email' => $data['email'], 'clinic' => $clinic])) {
            return $this->json(['error' => 'Cet email est déjà utilisé dans cet établissement.'], 409);
        }

        $user->setClinic($clinic);
        $em->persist($user);

        try {
            $em->flush();
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la création du compte : ' . $e->getMessage()], 500);
        }

        // Lier le user à un owner existant (même email + même clinique)
        $linking->linkUserToOwner($user, $clinic, $em);

        // Prometheus : incrémente le compteur d'inscriptions par rôle (affiché dans Grafana)
        $this->prometheusRegistry
            ->getOrRegisterCounter('asv', 'user_register_total', 'Nombre d\'inscriptions', ['role'])
            ->inc([$user->getRole()]);

        return $this->json($serializer->serializeRegisterResponseUser($user), 201);
    }

    #[Route('/login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $userRepo,
        ClinicRepository $clinicRepo,
        UserPasswordHasherInterface $hasher,
        JWTTokenManagerInterface $jwtManager,
        SerializerService $serializer,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (empty($data['email']) || empty($data['password'])) {
            return $this->json(['error' => 'Email et password requis.'], 400);
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => "Format d'email invalide."], 400);
        }

        // Si un clinicId est fourni, on cherche directement le bon compte
        if (!empty($data['clinicId'])) {
            $clinic = $clinicRepo->find($data['clinicId']);
            $user = $userRepo->findOneBy(['email' => $data['email'], 'clinic' => $clinic]);
        } else {
            $users = $userRepo->findBy(['email' => $data['email']]);

            // Plusieurs comptes avec cet email → demander de choisir la clinique
            if (count($users) > 1) {
                return $this->json([
                    'requiresClinicSelection' => true,
                    'clinics' => array_map(fn($u) => [
                        'id' => $u->getClinic()?->getId(),
                        'name' => $u->getClinic()?->getName() ?? 'Sans établissement',
                    ], $users),
                ]);
            }

            $user = $users[0] ?? null;
        }

        if (!$user || !$hasher->isPasswordValid($user, $data['password'])) {
            return $this->json(['error' => 'Identifiants invalides.'], 401);
        }

        // Prometheus : incrémente le compteur de connexions réussies par rôle (affiché dans Grafana)
        $this->prometheusRegistry
            ->getOrRegisterCounter('asv', 'user_login_total', 'Nombre de connexions réussies', ['role'])
            ->inc([$user->getRole()]);

        return $this->json($serializer->serializeLoginSuccessResponse($user, $jwtManager->create($user)));
    }

    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'role' => $user->getRole(),
            'clinicId' => $user->getClinic()?->getId(),
            'clinicName' => $user->getClinic()?->getName(),
        ]);
    }
}
