<?php

namespace App\Controller\Api;

use App\Entity\Owner;
use App\Repository\OwnerRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/owners')]
final class OwnerApiController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function index(OwnerRepository $repo): JsonResponse
    {
        $owners = array_map(fn($o) => $this->serialize($o), $repo->findAll());

        return $this->json($owners);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(Owner $owner): JsonResponse
    {
        return $this->json($this->serialize($owner));
    }

    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepo,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (empty($data['nom']) || empty($data['prenom'])) {
            return $this->json(['error' => 'Les champs nom et prenom sont obligatoires.'], 400);
        }

        $owner = new Owner();
        $owner->setNom($data['nom']);
        $owner->setPrenom($data['prenom']);
        $owner->setAdresse($data['adresse'] ?? null);
        $owner->setTelephone($data['telephone'] ?? null);
        $owner->setEmail($data['email'] ?? null);

        if (!empty($data['userId'])) {
            $user = $userRepo->find($data['userId']);
            if (!$user) {
                return $this->json(['error' => 'Utilisateur introuvable.'], 404);
            }
            $owner->setUser($user);
        }

        if (!empty($data['createdById'])) {
            $user = $userRepo->find($data['createdById']);
            if (!$user) {
                return $this->json(['error' => 'Utilisateur créateur introuvable.'], 404);
            }
            $owner->setCreatedBy($user);
        }

        $em->persist($owner);
        $em->flush();

        return $this->json($this->serialize($owner), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(
        Request $request,
        Owner $owner,
        EntityManagerInterface $em,
        UserRepository $userRepo,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (isset($data['nom'])) {
            $owner->setNom($data['nom']);
        }
        if (isset($data['prenom'])) {
            $owner->setPrenom($data['prenom']);
        }
        if (array_key_exists('adresse', $data)) {
            $owner->setAdresse($data['adresse']);
        }
        if (array_key_exists('telephone', $data)) {
            $owner->setTelephone($data['telephone']);
        }
        if (array_key_exists('email', $data)) {
            $owner->setEmail($data['email']);
        }

        if (array_key_exists('userId', $data)) {
            if ($data['userId'] === null) {
                $owner->setUser(null);
            } else {
                $user = $userRepo->find($data['userId']);
                if (!$user) {
                    return $this->json(['error' => 'Utilisateur introuvable.'], 404);
                }
                $owner->setUser($user);
            }
        }

        $em->flush();

        return $this->json($this->serialize($owner));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(Owner $owner, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($owner);
        $em->flush();

        return $this->json(null, 204);
    }

    private function serialize(Owner $o): array
    {
        return [
            'id'        => $o->getId(),
            'nom'       => $o->getNom(),
            'prenom'    => $o->getPrenom(),
            'adresse'   => $o->getAdresse(),
            'telephone' => $o->getTelephone(),
            'email'     => $o->getEmail(),
            'user'      => $o->getUser() ? [
                'id'   => $o->getUser()->getId(),
                'name' => $o->getUser()->getName(),
            ] : null,
            'createdBy' => $o->getCreatedBy() ? [
                'id'   => $o->getCreatedBy()->getId(),
                'name' => $o->getCreatedBy()->getName(),
            ] : null,
            'createdAt' => $o->getCreatedAt()?->format('c'),
        ];
    }
}
