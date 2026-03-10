<?php

namespace App\Controller\Api;

use App\Entity\Animal;
use App\Repository\AnimalRepository;
use App\Repository\OwnerRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/animals')]
final class AnimalApiController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function index(AnimalRepository $repo): JsonResponse
    {
        $animals = array_map(fn($a) => $this->serialize($a), $repo->findAll());

        return $this->json($animals);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(Animal $animal): JsonResponse
    {
        return $this->json($this->serialize($animal));
    }

    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        OwnerRepository $ownerRepo,
        UserRepository $userRepo,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (empty($data['nom']) || empty($data['espece'])) {
            return $this->json(['error' => 'Les champs nom et espece sont obligatoires.'], 400);
        }

        $animal = new Animal();
        $animal->setNom($data['nom']);
        $animal->setEspece($data['espece']);
        $animal->setRace($data['race'] ?? null);
        $animal->setRemarques($data['remarques'] ?? null);

        if (!empty($data['dateNaissance'])) {
            $animal->setDateNaissance(new \DateTime($data['dateNaissance']));
        }

        if (!empty($data['proprietaireId'])) {
            $owner = $ownerRepo->find($data['proprietaireId']);
            if (!$owner) {
                return $this->json(['error' => 'Propriétaire introuvable.'], 404);
            }
            $animal->setProprietaire($owner);
        }

        if (!empty($data['createdById'])) {
            $user = $userRepo->find($data['createdById']);
            if (!$user) {
                return $this->json(['error' => 'Utilisateur introuvable.'], 404);
            }
            $animal->setCreatedBy($user);
        }

        $em->persist($animal);
        $em->flush();

        return $this->json($this->serialize($animal), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(
        Request $request,
        Animal $animal,
        EntityManagerInterface $em,
        OwnerRepository $ownerRepo,
        UserRepository $userRepo,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (isset($data['nom'])) {
            $animal->setNom($data['nom']);
        }
        if (isset($data['espece'])) {
            $animal->setEspece($data['espece']);
        }
        if (array_key_exists('race', $data)) {
            $animal->setRace($data['race']);
        }
        if (array_key_exists('remarques', $data)) {
            $animal->setRemarques($data['remarques']);
        }
        if (array_key_exists('dateNaissance', $data)) {
            $animal->setDateNaissance($data['dateNaissance'] ? new \DateTime($data['dateNaissance']) : null);
        }

        if (array_key_exists('proprietaireId', $data)) {
            if ($data['proprietaireId'] === null) {
                $animal->setProprietaire(null);
            } else {
                $owner = $ownerRepo->find($data['proprietaireId']);
                if (!$owner) {
                    return $this->json(['error' => 'Propriétaire introuvable.'], 404);
                }
                $animal->setProprietaire($owner);
            }
        }

        if (array_key_exists('createdById', $data)) {
            if ($data['createdById'] === null) {
                $animal->setCreatedBy(null);
            } else {
                $user = $userRepo->find($data['createdById']);
                if (!$user) {
                    return $this->json(['error' => 'Utilisateur introuvable.'], 404);
                }
                $animal->setCreatedBy($user);
            }
        }

        $em->flush();

        return $this->json($this->serialize($animal));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(Animal $animal, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($animal);
        $em->flush();

        return $this->json(null, 204);
    }

    private function serialize(Animal $a): array
    {
        return [
            'id'            => $a->getId(),
            'nom'           => $a->getNom(),
            'espece'        => $a->getEspece(),
            'race'          => $a->getRace(),
            'dateNaissance' => $a->getDateNaissance()?->format('Y-m-d'),
            'remarques'     => $a->getRemarques(),
            'proprietaire'  => $a->getProprietaire() ? [
                'id'     => $a->getProprietaire()->getId(),
                'nom'    => $a->getProprietaire()->getNom(),
                'prenom' => $a->getProprietaire()->getPrenom(),
            ] : null,
            'createdBy' => $a->getCreatedBy() ? [
                'id'   => $a->getCreatedBy()->getId(),
                'name' => $a->getCreatedBy()->getName(),
            ] : null,
            'createdAt' => $a->getCreatedAt()?->format('c'),
        ];
    }
}
