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

        // Validation basique de l'email (commun aux deux flux)
        if (empty($data['email'])) {
            return $this->json(['error' => 'Email requis.'], 400);
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => "Format d'email invalide."], 400);
        }

        // Flux A : activation de tous les pré-comptes liés à cet email
        $pendingUsers = $userRepo->findAllPendingByEmail($data['email']);
        if (!empty($pendingUsers)) {
            if (empty($data['password'])) {
                return $this->json(['error' => 'Le mot de passe est obligatoire.'], 400);
            }
            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{6,}$/', $data['password'])) {
                return $this->json(['error' => 'Le mot de passe doit contenir au moins 6 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.'], 400);
            }
            foreach ($pendingUsers as $pendingUser) {
                $pendingUser->setPassword($hasher->hashPassword($pendingUser, $data['password']));
                $this->prometheusRegistry
                    ->getOrRegisterCounter('asv', 'user_register_total', 'Nombre d\'inscriptions', ['role'])
                    ->inc([$pendingUser->getRole()]);
            }
            $em->flush();

            return $this->json($serializer->serializeRegisterResponseUser($pendingUsers[0]), 201);
        }

        // Flux B : inscription classique — seuls responsable et client sont autorisés
        if ($error = $validator->validateUserCreate($data)) {
            return $this->json(['error' => $error], 400);
        }

        if ($data['role'] !== 'responsable') {
            return $this->json(['error' => 'Ce rôle doit être ajouté par un responsable depuis l\'application.'], 403);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setName($data['name']);
        $user->setRole($data['role']);
        $user->setPassword($hasher->hashPassword($user, $data['password']));

        $allowedTypes = ['clinique', 'refuge', 'association'];
        $clinic = null;

        if ($data['role'] === 'responsable') {
            if (empty($data['clinicName'])) {
                return $this->json(['error' => 'Un responsable doit créer un établissement (clinicName requis).'], 400);
            }
            $clinic = new Clinic();
            $clinic->setName($data['clinicName']);
            if (!empty($data['clinicType']) && in_array($data['clinicType'], $allowedTypes, true)) {
                $clinic->setType($data['clinicType']);
            }
            $em->persist($clinic);
        }
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

        $linking->linkUserToOwner($user, $clinic, $em);

        $this->prometheusRegistry
            ->getOrRegisterCounter('asv', 'user_register_total', 'Nombre d\'inscriptions', ['role'])
            ->inc([$user->getRole()]);

        return $this->json($serializer->serializeRegisterResponseUser($user), 201);
    }

    #[Route('/check-pending', methods: ['GET'])]
    public function checkPending(Request $request, UserRepository $userRepo): JsonResponse
    {
        $email = $request->query->get('email', '');

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['pending' => false]);
        }

        $pendingUsers = $userRepo->findAllPendingByEmail($email);

        if (empty($pendingUsers)) {
            return $this->json(['pending' => false]);
        }

        return $this->json([
            'pending' => true,
            'name'    => $pendingUsers[0]->getName(),
            'role'    => $pendingUsers[0]->getRole(),
        ]);
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
                        'id'   => $u->getClinic()?->getId(),
                        'name' => $u->getClinic()?->getName(),
                    ], $users),
                ]);
            }

            $user = $users[0] ?? null;
        }

        if (!$user) {
            return $this->json(['error' => 'Identifiants invalides.'], 401);
        }

        if ($user->getPassword() === null) {
            return $this->json(['error' => 'Ce compte n\'a pas encore été activé. Rendez-vous sur l\'écran d\'inscription pour définir votre mot de passe.'], 403);
        }

        if (!$hasher->isPasswordValid($user, $data['password'])) {
            return $this->json(['error' => 'Identifiants invalides.'], 401);
        }

        // Prometheus : incrémente le compteur de connexions réussies par rôle (affiché dans Grafana)
        $this->prometheusRegistry
            ->getOrRegisterCounter('asv', 'user_login_total', 'Nombre de connexions réussies', ['role'])
            ->inc([$user->getRole()]);

        return $this->json($serializer->serializeLoginSuccessResponse($user, $jwtManager->create($user)));
    }

    #[Route('/me', methods: ['GET'])]
    public function me(SerializerService $serializer): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json($serializer->serializeLoginResponseUser($user));
    }
}
