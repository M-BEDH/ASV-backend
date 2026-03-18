<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/users')]
final class UserApiController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function index(UserRepository $repo): JsonResponse
    {
        /** @var \App\Entity\User $me */
        $me = $this->getUser();
        $clinic = $me->getClinic();

        $users = $clinic
            ? $repo->findBy(['clinic' => $clinic])
            : $repo->findBy(['clinic' => null]);

        return $this->json(array_map(fn($u) => $this->serialize($u), $users));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(User $user): JsonResponse
    {
        /** @var \App\Entity\User $me */
        $me = $this->getUser();

        if ($user->getClinic()?->getId() !== $me->getClinic()?->getId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        return $this->json($this->serialize($user));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['email']) || empty($data['name']) || empty($data['role'])) {
            return $this->json(['error' => 'Les champs email, name et role sont obligatoires.'], 400);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setName($data['name']);
        $user->setRole($data['role']);

        $em->persist($user);
        $em->flush();

        return $this->json($this->serialize($user), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(Request $request, User $user, EntityManagerInterface $em): JsonResponse
    {
        /** @var \App\Entity\User $me */
        $me = $this->getUser();

        if ($user->getClinic()?->getId() !== $me->getClinic()?->getId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['email'])) {
            $user->setEmail($data['email']);
        }
        if (isset($data['name'])) {
            $user->setName($data['name']);
        }
        // Le rôle n'est pas modifiable après l'inscription

        $em->flush();

        return $this->json($this->serialize($user));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(User $user, EntityManagerInterface $em): JsonResponse
    {
        /** @var \App\Entity\User $me */
        $me = $this->getUser();

        if (!in_array($me->getRole(), ['responsable', 'veterinaire', 'assistant'], true)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        if ($user->getClinic()?->getId() !== $me->getClinic()?->getId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $em->remove($user);
        $em->flush();

        return $this->json(null, 204);
    }

    private function serialize(User $u): array
    {
        return [
            'id'        => $u->getId(),
            'email'     => $u->getEmail(),
            'name'      => $u->getName(),
            'role'      => $u->getRole(),
            'createdAt' => $u->getCreatedAt()?->format('c'),
        ];
    }
}
