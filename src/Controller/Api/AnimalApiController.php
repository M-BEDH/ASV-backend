<?php

namespace App\Controller\Api;

use App\Entity\Animal;
use App\Entity\User;
use App\Repository\AnimalRepository;
use App\Repository\MedicalConsultationRepository;
use App\Repository\OwnerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/animals')]
final class AnimalApiController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function index(AnimalRepository $repo, OwnerRepository $ownerRepo): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();

        if ($me->getRole() === 'client') {
            $owner = $ownerRepo->findOneBy(['email' => $me->getEmail()]);
            if (!$owner) {
                return $this->json([]);
            }
            $animals = $repo->findBy(['proprietaire' => $owner]);
            return $this->json(array_map(fn($a) => $this->serialize($a), $animals));
        }

        $clinic = $me->getClinic();
        $animals = $clinic
            ? $repo->findBy(['clinic' => $clinic])
            : $repo->findBy(['clinic' => null]);

        return $this->json(array_map(fn($a) => $this->serialize($a), $animals));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id, AnimalRepository $repo): JsonResponse
    {
        $animal = $repo->find($id);
        if (!$animal) {
            return $this->json(['error' => 'Animal introuvable.'], 404);
        }

        /** @var User $me */
        $me = $this->getUser();
        if ($animal->getClinic()?->getId() !== $me->getClinic()?->getId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        return $this->json($this->serialize($animal));
    }

    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        OwnerRepository $ownerRepo,
    ): JsonResponse {
        /** @var User $me */
        $me = $this->getUser();

        if ($me->getRole() === 'client') {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (empty($data['nom']) || empty($data['espece'])) {
            return $this->json(['error' => 'Les champs nom et espece sont obligatoires.'], 400);
        }

        $animal = new Animal();
        $animal->setNom($data['nom']);
        $animal->setEspece($data['espece']);
        $animal->setRace($data['race'] ?? null);
        $animal->setRemarques($data['remarques'] ?? null);
        $animal->setCreatedBy($me);
        $animal->setClinic($me->getClinic());

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

        $em->persist($animal);
        $em->flush();

        return $this->json($this->serialize($animal), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(
        string $id,
        Request $request,
        AnimalRepository $repo,
        EntityManagerInterface $em,
        OwnerRepository $ownerRepo,
    ): JsonResponse {
        /** @var User $me */
        $me = $this->getUser();

        if ($me->getRole() === 'client') {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $animal = $repo->find($id);
        if (!$animal) {
            return $this->json(['error' => 'Animal introuvable.'], 404);
        }

        if ($animal->getClinic()?->getId() !== $me->getClinic()?->getId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

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

        $em->flush();

        return $this->json($this->serialize($animal));
    }

    #[Route('/{id}/consultations', methods: ['GET'])]
    public function consultations(string $id, AnimalRepository $repo, MedicalConsultationRepository $consultationRepo): JsonResponse
    {
        $animal = $repo->find($id);
        if (!$animal) {
            return $this->json(['error' => 'Animal introuvable.'], 404);
        }

        /** @var User $me */
        $me = $this->getUser();
        if ($me->getRole() !== 'client' && $animal->getClinic()?->getId() !== $me->getClinic()?->getId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $consultations = $consultationRepo->findByAnimalWithVet($id);

        return $this->json(array_map(fn($c) => [
            'id' => $c->getId(),
            'dateConsultation' => $c->getDateConsultation()?->format('c'),
            'motif' => $c->getMotif(),
            'compteRendu' => $c->getCompteRendu(),
            'traitements' => $c->getTraitements(),
            'veterinaire' => $c->getVeterinaire() ? [
                'id' => $c->getVeterinaire()->getId(),
                'name' => $c->getVeterinaire()->getName(),
            ] : null,
        ], $consultations));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id, AnimalRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();

        if ($me->getRole() === 'client') {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $animal = $repo->find($id);
        if (!$animal) {
            return $this->json(['error' => 'Animal introuvable.'], 404);
        }

        if ($animal->getClinic()?->getId() !== $me->getClinic()?->getId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $em->remove($animal);
        $em->flush();

        return $this->json(null, 204);
    }

    private function serialize(Animal $a): array
    {
        return [
            'id' => $a->getId(),
            'nom' => $a->getNom(),
            'espece' => $a->getEspece(),
            'race' => $a->getRace(),
            'dateNaissance' => $a->getDateNaissance()?->format('Y-m-d'),
            'remarques' => $a->getRemarques(),
            'proprietaire' => $a->getProprietaire() ? [
                'id' => $a->getProprietaire()->getId(),
                'nom' => $a->getProprietaire()->getNom(),
                'prenom' => $a->getProprietaire()->getPrenom(),
            ] : null,
            'createdBy' => $a->getCreatedBy() ? [
                'id' => $a->getCreatedBy()->getId(),
                'name' => $a->getCreatedBy()->getName(),
            ] : null,
            'clinicId' => $a->getClinic()?->getId(),
            'createdAt' => $a->getCreatedAt()?->format('c'),
        ];
    }
}
