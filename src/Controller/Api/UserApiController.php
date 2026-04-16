<?php

namespace App\Controller\Api;

use App\Constant\RoleConstants;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\SerializerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/users')]
final class UserApiController extends AbstractController
{
    use ClinicAccessTrait;

    #[Route('', methods: ['GET'])]
    public function index(UserRepository $repo, SerializerService $serializer): JsonResponse
    {
        /** @var \App\Entity\User $me */
        $me = $this->getUser();
        $clinic = $me->getClinic();

        $users = $clinic
            ? $repo->findBy(['clinic' => $clinic])
            : $repo->findBy(['clinic' => null]);

        return $this->json(array_map(fn($u) => $serializer->serializeUser($u), $users));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(User $user, SerializerService $serializer): JsonResponse
    {
        // Vérifie que l'utilisateur appartient à la même clinique que l'utilisateur connecté
        if (!$this->memeClinic($user)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        return $this->json($serializer->serializeUser($user));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, SerializerService $serializer, UserRepository $repo): JsonResponse
    {
        /** @var \App\Entity\User $me */
        $me = $this->getUser();

        if ($me->getRole() !== 'responsable') {
            return $this->json(['error' => 'Seul un responsable peut ajouter des collaborateurs.'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (empty($data['email']) || empty($data['name']) || empty($data['role'])) {
            return $this->json(['error' => 'Les champs email, name et role sont obligatoires.'], 400);
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => "Format d'email invalide."], 400);
        }
        if (!in_array($data['role'], RoleConstants::ASSIGNABLE_BY_RESPONSABLE, true)) {
            return $this->json(['error' => 'Rôle invalide. Valeurs acceptées : ' . implode(', ', RoleConstants::ASSIGNABLE_BY_RESPONSABLE) . '.'], 400);
        }

        $clinic = $me->getClinic();

        if ($repo->findOneBy(['email' => $data['email'], 'clinic' => $clinic])) {
            return $this->json(['error' => 'Un compte existe déjà pour cet email dans cet établissement.'], 409);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setName($data['name']);
        $user->setRole($data['role']);
        $user->setIsVet($data['role'] === 'veterinaire');
        $user->setClinic($clinic);
        // Pas de mot de passe : le compte sera activé lors de la première connexion du collaborateur

        $em->persist($user);
        $em->flush();

        return $this->json($serializer->serializeUser($user), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(Request $request, User $user, EntityManagerInterface $em, SerializerService $serializer): JsonResponse
    {
        // Vérifie que l'utilisateur à modifier appartient à la même clinique que l'utilisateur connecté
        if (!$this->memeClinic($user)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['role'])) {
            /** @var \App\Entity\User $me */
            $me = $this->getUser();
            if ($me->getRole() !== 'responsable') {
                return $this->json(['error' => 'Seul un responsable peut modifier le rôle d\'un collaborateur.'], 403);
            }
            if (!in_array($data['role'], RoleConstants::ASSIGNABLE_BY_RESPONSABLE, true)) {
                return $this->json(['error' => 'Rôle invalide. Valeurs acceptées : ' . implode(', ', RoleConstants::ASSIGNABLE_BY_RESPONSABLE) . '.'], 400);
            }
            $user->setRole($data['role']);
            $user->setIsVet($data['role'] === 'veterinaire');
        }

        if (isset($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return $this->json(['error' => "Format d'email invalide."], 400);
            }
            $user->setEmail($data['email']);
        }
        if (isset($data['name'])) {
            $user->setName($data['name']);
        }

        $em->flush();

        return $this->json($serializer->serializeUser($user));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(User $user, EntityManagerInterface $em): JsonResponse
    {
        /** @var \App\Entity\User $me */
        $me = $this->getUser();

        if (!in_array($me->getRole(), RoleConstants::CAN_DELETE_USER, true)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        // Vérifie que l'utilisateur à supprimer appartient à la même clinique que l'utilisateur connecté
        if (!$this->memeClinic($user)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $user->anonymize();
        $em->flush();

        return $this->json(null, 204);
    }

}
