<?php

namespace App\Controller\Api;

use App\Entity\MedicalConsultation;
use App\Entity\User;
use App\Repository\AnimalRepository;
use App\Repository\MedicalConsultationRepository;
use App\Repository\OwnerRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/consultations')]
final class MedicalConsultationApiController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function index(MedicalConsultationRepository $repo, OwnerRepository $ownerRepo): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();

        if ($me->getRole() === 'client') {
            $owner = $ownerRepo->findOneBy(['email' => $me->getEmail()]);
            if (!$owner) {
                return $this->json([]);
            }
            $consultations = $repo->findByOwnerWithRelations($owner->getId());
            return $this->json(array_map(fn($c) => $this->serialize($c), $consultations));
        }

        $clinic = $me->getClinic();
        $consultations = $repo->findByClinicWithRelations($clinic?->getId());

        return $this->json(array_map(fn($c) => $this->serialize($c), $consultations));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id, MedicalConsultationRepository $repo): JsonResponse
    {
        $consultation = $repo->find($id);
        if (!$consultation) {
            return $this->json(['error' => 'Consultation introuvable.'], 404);
        }

        /** @var User $me */
        $me = $this->getUser();
        if ($consultation->getClinic()?->getId() !== $me->getClinic()?->getId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        return $this->json($this->serialize($consultation));
    }

    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        AnimalRepository $animalRepo,
        UserRepository $userRepo,
    ): JsonResponse {
        /** @var User $me */
        $me = $this->getUser();

        if ($me->getRole() === 'client') {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (empty($data['animalId']) || empty($data['dateConsultation']) || empty($data['motif'])) {
            return $this->json(['error' => 'Les champs animalId, dateConsultation et motif sont obligatoires.'], 400);
        }

        $animal = $animalRepo->find($data['animalId']);
        if (!$animal) {
            return $this->json(['error' => 'Animal introuvable.'], 404);
        }

        $consultation = new MedicalConsultation();
        $consultation->setAnimal($animal);
        $consultation->setMotif($data['motif']);
        $consultation->setDateConsultation(new \DateTime($data['dateConsultation']));
        $consultation->setCompteRendu($data['compteRendu'] ?? null);
        $consultation->setTraitements($data['traitements'] ?? null);
        $consultation->setClinic($me->getClinic());

        // Default to the authenticated user as veterinaire; allow override
        $vetId = $data['veterinaireId'] ?? null;
        if ($vetId) {
            $vet = $userRepo->find($vetId);
            if (!$vet) {
                return $this->json(['error' => 'Vétérinaire introuvable.'], 404);
            }
            $consultation->setVeterinaire($vet);
        } else {
            $consultation->setVeterinaire($me);
        }

        $em->persist($consultation);
        $em->flush();

        return $this->json($this->serialize($consultation), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(
        string $id,
        Request $request,
        MedicalConsultationRepository $repo,
        EntityManagerInterface $em,
        AnimalRepository $animalRepo,
        UserRepository $userRepo,
    ): JsonResponse {
        $consultation = $repo->find($id);
        if (!$consultation) {
            return $this->json(['error' => 'Consultation introuvable.'], 404);
        }

        /** @var User $me */
        $me = $this->getUser();

        if ($me->getRole() === 'client') {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        if ($consultation->getClinic()?->getId() !== $me->getClinic()?->getId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['motif'])) {
            $consultation->setMotif($data['motif']);
        }
        if (isset($data['dateConsultation'])) {
            $consultation->setDateConsultation(new \DateTime($data['dateConsultation']));
        }
        if (array_key_exists('compteRendu', $data)) {
            $consultation->setCompteRendu($data['compteRendu']);
        }
        if (array_key_exists('traitements', $data)) {
            $consultation->setTraitements($data['traitements']);
        }

        if (array_key_exists('animalId', $data)) {
            if ($data['animalId'] === null) {
                $consultation->setAnimal(null);
            } else {
                $animal = $animalRepo->find($data['animalId']);
                if (!$animal) {
                    return $this->json(['error' => 'Animal introuvable.'], 404);
                }
                $consultation->setAnimal($animal);
            }
        }

        if (array_key_exists('veterinaireId', $data)) {
            if ($data['veterinaireId'] === null) {
                $consultation->setVeterinaire(null);
            } else {
                $vet = $userRepo->find($data['veterinaireId']);
                if (!$vet) {
                    return $this->json(['error' => 'Vétérinaire introuvable.'], 404);
                }
                $consultation->setVeterinaire($vet);
            }
        }

        $em->flush();

        return $this->json($this->serialize($consultation));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id, MedicalConsultationRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $consultation = $repo->find($id);
        if (!$consultation) {
            return $this->json(['error' => 'Consultation introuvable.'], 404);
        }

        /** @var User $me */
        $me = $this->getUser();

        if ($me->getRole() === 'client') {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        if ($consultation->getClinic()?->getId() !== $me->getClinic()?->getId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $em->remove($consultation);
        $em->flush();

        return $this->json(null, 204);
    }

    private function serialize(MedicalConsultation $c): array
    {
        return [
            'id' => $c->getId(),
            'dateConsultation' => $c->getDateConsultation()?->format('c'),
            'motif' => $c->getMotif(),
            'compteRendu' => $c->getCompteRendu(),
            'traitements' => $c->getTraitements(),
            'clinicId' => $c->getClinic()?->getId(),
            'animal' => $c->getAnimal() ? [
                'id' => $c->getAnimal()->getId(),
                'nom' => $c->getAnimal()->getNom(),
                'espece' => $c->getAnimal()->getEspece(),
            ] : null,
            'veterinaire' => $c->getVeterinaire() ? [
                'id' => $c->getVeterinaire()->getId(),
                'name' => $c->getVeterinaire()->getName(),
            ] : null,
            'createdAt' => $c->getCreatedAt()?->format('c'),
        ];
    }
}
