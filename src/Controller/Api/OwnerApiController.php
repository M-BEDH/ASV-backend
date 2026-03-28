<?php

namespace App\Controller\Api;

use App\Entity\Owner;
use App\Entity\User;
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
        /** @var User $me */
        $me = $this->getUser();

        if ($me->getRole() === 'client') {
            $owners = $repo->findBy(['user' => $me]);
            return $this->json(array_map(fn($o) => $this->serialize($o), $owners));
        }

        $clinic = $me->getClinic();
        $owners = $clinic
            ? $repo->findBy(['clinic' => $clinic])
            : $repo->findBy(['clinic' => null]);

        return $this->json(array_map(fn($o) => $this->serialize($o), $owners));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id, OwnerRepository $repo): JsonResponse
    {
        $owner = $repo->find($id);
        if (!$owner) {
            return $this->json(['error' => 'Propriétaire introuvable.'], 404);
        }

        /** @var User $me */
        $me = $this->getUser();
        if ($owner->getClinic()?->getId() !== $me->getClinic()?->getId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        return $this->json($this->serialize($owner));
    }

    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepo,
        OwnerRepository $ownerRepo,
    ): JsonResponse {
        /** @var User $me */
        $me = $this->getUser();

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
        $owner->setCreatedBy($me);

        if ($me->getRole() === 'client') {
            // Un client ne peut avoir qu'un seul profil owner par établissement
            $existing = $ownerRepo->findOneBy(['user' => $me]);
            if ($existing !== null) {
                return $this->json(['error' => 'Vous avez déjà un profil propriétaire.'], 409);
            }

            $owner->setUser($me);
            $owner->setClinic($me->getClinic());
            if (!empty($data['email']) && $data['email'] !== $me->getEmail()) {
                $me->setEmail($data['email']);
            }
        } else {
            $clinic = $me->getClinic();
            $owner->setClinic($clinic);

            // Lier un user client existant (même email + même clinique)
            if (!empty($data['email']) && $clinic !== null) {
                $existingUser = $userRepo->findOneBy(['email' => $data['email'], 'clinic' => $clinic]);
                if ($existingUser !== null && $existingUser->getRole() === 'client') {
                    $owner->setUser($existingUser);
                }
            }
        }

        $em->persist($owner);
        $em->flush();

        return $this->json($this->serialize($owner), 201);
    }

    private function canAccess(Owner $owner): bool
    {
        /** @var User $me */
        $me = $this->getUser();

        if ($me->getRole() === 'client') {
            return $owner->getUser()?->getId() === $me->getId();
        }

        return $owner->getClinic()?->getId() === $me->getClinic()?->getId();
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(
        string $id,
        Request $request,
        OwnerRepository $repo,
        EntityManagerInterface $em,
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
            if ($me->getRole() === 'client' && !empty($data['email']) && $data['email'] !== $me->getEmail()) {
                $me->setEmail($data['email']);
            }
        }

        $em->flush();

        return $this->json($this->serialize($owner));
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

        $em->remove($owner);
        $em->flush();

        return $this->json(null, 204);
    }

    private function serialize(Owner $o): array
    {
        return [
            'id' => $o->getId(),
            'nom' => $o->getNom(),
            'prenom' => $o->getPrenom(),
            'adresse' => $o->getAdresse(),
            'telephone' => $o->getTelephone(),
            'email' => $o->getEmail(),
            'clinicId' => $o->getClinic()?->getId(),
            'createdBy' => $o->getCreatedBy() ? [
                'id' => $o->getCreatedBy()->getId(),
                'name' => $o->getCreatedBy()->getName(),
            ] : null,
            'createdAt' => $o->getCreatedAt()?->format('c'),
        ];
    }
}
