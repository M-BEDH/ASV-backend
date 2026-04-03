<?php

namespace App\Controller\Api;

use App\Entity\Owner;
use App\Entity\User;
use App\Repository\OwnerRepository;
use App\Service\ApiValidator;
use App\Service\SerializerService;
use App\Service\UserOwnerLinkingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/owners')]
final class OwnerApiController extends AbstractController
{
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
        $owners = $clinic
            ? $repo->findBy(['clinic' => $clinic])
            : $repo->findBy(['clinic' => null]);

        return $this->json(array_map(fn($o) => $serializer->serializeOwner($o), $owners));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id, OwnerRepository $repo, SerializerService $serializer): JsonResponse
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

        return $this->json($serializer->serializeOwner($owner));
    }

    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        OwnerRepository $ownerRepo,
        ApiValidator $validator,
        SerializerService $serializer,
        UserOwnerLinkingService $linking,
    ): JsonResponse {
        /** @var User $me */
        $me = $this->getUser();

        $data = json_decode($request->getContent(), true);

        if ($error = $validator->validateOwnerCreate($data, $me->getClinic())) {
            return $this->json(['error' => $error], 400);
        }

        $owner = new Owner();
        $owner->setNom($data['nom']);
        $owner->setPrenom($data['prenom']);
        $owner->setAdresse($data['adresse'] ?? null);
        $owner->setTelephone($data['telephone'] ?? null);
        $owner->setEmail($data['email']);
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
            $linking->linkOwnerToUser($owner, $clinic);
        }

        $em->persist($owner);
        $em->flush();

        return $this->json($serializer->serializeOwner($owner), 201);
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

        $em->remove($owner);
        $em->flush();

        return $this->json(null, 204);
    }
}
