<?php

namespace App\Controller\Api;

use App\Entity\Owner;
use App\Entity\User;
use App\Repository\OwnerRepository;
use App\Repository\UserRepository;
use App\Service\ApiValidator;
use App\Service\SerializerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/owners')]
final class OwnerApiController extends AbstractController
{
    use ClinicAccessTrait;
    #[Route('', methods: ['GET'])]
    public function index(OwnerRepository $repo, SerializerService $serializer): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();

        if ($me->getRole() === 'client') {
            $owners = $repo->findBy(['user' => $me]);
            return $this->json(array_map(fn($o) => $serializer->serializeOwner($o), $owners));
        }

        $clinic = $me->getClinic();
        $owners = $clinic ? $repo->findByClinic($clinic) : [];

        return $this->json(array_map(fn($o) => $serializer->serializeOwner($o), $owners));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id, OwnerRepository $repo, SerializerService $serializer): JsonResponse
    {
        $owner = $repo->find($id);
        if (!$owner) {
            return $this->json(['error' => 'Propriétaire introuvable.'], 404);
        }

        if (!$this->canAccess($owner)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        return $this->json($serializer->serializeOwner($owner));
    }

    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        OwnerRepository $ownerRepo,
        UserRepository $userRepo,
        ApiValidator $validator,
        SerializerService $serializer,
    ): JsonResponse {
        /** @var User $me */
        $me = $this->getUser();

        $data = json_decode($request->getContent(), true);

        if ($error = $validator->validateOwnerCreate($data, $me->getClinic())) {
            return $this->json(['error' => $error], 400);
        }

        if ($me->getRole() === 'benevole') {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        if ($me->getRole() === 'client') {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        // Staff (véto, responsable, assistant...)
        $clinic = $me->getClinic();

        // Si un Owner avec cet email existe déjà → on ajoute juste la clinique
        $existing = $ownerRepo->findOneBy(['email' => $data['email']]);
        if ($existing !== null) {
            if ($clinic && !$existing->hasClinic($clinic)) {
                $existing->addClinic($clinic);
            }

            $linkedUser = $existing->getUser();
            if (!$linkedUser) {
                $linkedUser = $userRepo->findOneBy(['email' => $data['email']]);
                if (!$linkedUser) {
                    $linkedUser = new User();
                    $linkedUser->setEmail($data['email']);
                    $linkedUser->setName(trim($existing->getPrenom() . ' ' . $existing->getNom()));
                    $linkedUser->setRole('client');
                    $em->persist($linkedUser);
                }
                $existing->setUser($linkedUser);
            }

            if ($linkedUser->getRole() === 'client' && $clinic && !$linkedUser->hasClinic($clinic)) {
                $linkedUser->addClinic($clinic);
            }

            $em->flush();
            return $this->json($serializer->serializeOwner($existing), 200);
        }

        // Nouvel Owner
        $owner = new Owner();
        $owner->setNom($data['nom']);
        $owner->setPrenom($data['prenom']);
        $owner->setAdresse($data['adresse'] ?? null);
        $owner->setTelephone($data['telephone'] ?? null);
        $owner->setEmail($data['email']);
        $owner->setCreatedBy($me);
        if ($clinic) {
            $owner->addClinic($clinic);
        }

        $em->persist($owner);
        $em->flush();

        // Pré-compte client : un seul User par email, multi-clinique via ManyToMany
        $existingUser = $userRepo->findOneBy(['email' => $data['email']]);
        if (!$existingUser) {
            $clientUser = new User();
            $clientUser->setEmail($data['email']);
            $clientUser->setName(trim($data['prenom'] . ' ' . $data['nom']));
            $clientUser->setRole('client');
            if ($clinic) {
                $clientUser->addClinic($clinic);
            }
            $owner->setUser($clientUser);
            $em->persist($clientUser);
            $em->flush();
        } elseif ($existingUser->getRole() === 'client') {
            if ($clinic && !$existingUser->hasClinic($clinic)) {
                $existingUser->addClinic($clinic);
            }
            if ($owner->getUser() === null) {
                $owner->setUser($existingUser);
            }
            $em->flush();
        }

        return $this->json($serializer->serializeOwner($owner), 201);
    }

    private function canAccess(Owner $owner): bool
    {
        /** @var User $me */
        $me = $this->getUser();

        if ($me->getRole() === 'client') {
            return $owner->getUser()?->getId() === $me->getId();
        }

        // Vérifie que le propriétaire partage au moins une clinique avec l'utilisateur connecté
        return $this->hasSharedClinic($owner);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(
        string $id,
        Request $request,
        OwnerRepository $repo,
        EntityManagerInterface $em,
        ApiValidator $validator,
        SerializerService $serializer,
    ): JsonResponse {
        $owner = $repo->find($id);
        if (!$owner) {
            return $this->json(['error' => 'Propriétaire introuvable.'], 404);
        }

        if (!$this->canAccess($owner)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        /** @var User $me */
        $me = $this->getUser();

        if ($me->getRole() === 'benevole') {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if ($error = $validator->validateOwnerUpdate($data, $owner)) {
            return $this->json(['error' => $error], 400);
        }

        if (isset($data['nom'])) {
            $owner->setNom($data['nom']);
        }
        if (isset($data['prenom'])) {
            $owner->setPrenom($data['prenom']);
        }
        // Synchronise le nom du User client avec celui de son Owner
        if (isset($data['nom']) || isset($data['prenom'])) {
            $linkedUser = $owner->getUser();
            if ($linkedUser && $linkedUser->getRole() === 'client') {
                $linkedUser->setName(trim(($data['prenom'] ?? $owner->getPrenom()) . ' ' . ($data['nom'] ?? $owner->getNom())));
            }
        }
        if (array_key_exists('adresse', $data)) {
            $owner->setAdresse($data['adresse']);
        }
        if (array_key_exists('telephone', $data)) {
            $owner->setTelephone($data['telephone']);
        }
        if (array_key_exists('email', $data)) {
            $owner->setEmail($data['email']);
            if ($me->getRole() === 'client' && $data['email'] !== $me->getEmail()) {
                $me->setEmail($data['email']);
            }
        }

        $em->flush();

        return $this->json($serializer->serializeOwner($owner));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id, OwnerRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $owner = $repo->find($id);
        if (!$owner) {
            return $this->json(['error' => 'Propriétaire introuvable.'], 404);
        }

        if (!$this->canAccess($owner)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        /** @var User $me */
        $me = $this->getUser();

        if ($me->getRole() === 'benevole') {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $linkedUser = $owner->getUser();
        if ($linkedUser && $linkedUser->getRole() === 'client') {
            $linkedUser->anonymize();
        }

        $owner->anonymize();
        $em->flush();

        return $this->json(null, 204);
    }
}
