<?php

namespace App\Controller\Api;

use App\Entity\MedicalConsultation;
use App\Entity\User;
use App\Repository\AnimalRepository;
use App\Repository\MedicalConsultationRepository;
use App\Repository\OwnerRepository;
use App\Repository\UserRepository;
use App\Service\SerializerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/consultations')]
final class MedicalConsultationApiController extends AbstractController
{
    use ClinicAccessTrait;

    #[Route('', methods: ['GET'])]
    public function index(MedicalConsultationRepository $repo, OwnerRepository $ownerRepo, SerializerService $serializer): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();

        if ($me->getRole() === 'client') {
            $owner = $ownerRepo->findOneBy(['email' => $me->getEmail()]);
            if (!$owner) {
                return $this->json([]);
            }
            $consultations = $repo->findByOwnerWithRelations($owner->getId());
            return $this->json(array_map(fn($c) => $serializer->serializeConsultation($c), $consultations));
        }

        $clinic = $me->getClinic();
        if (!$clinic) return $this->json([]);
        $consultations = $repo->findByClinicAccess($clinic);

        return $this->json(array_map(fn($c) => $serializer->serializeConsultation($c), $consultations));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id, MedicalConsultationRepository $repo, SerializerService $serializer): JsonResponse
    {
        $consultation = $repo->find($id);
        if (!$consultation) {
            return $this->json(['error' => 'Consultation introuvable.'], 404);
        }

        if (!$this->doShowConsultation($consultation)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        return $this->json($serializer->serializeConsultation($consultation));
    }

    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        AnimalRepository $animalRepo,
        UserRepository $userRepo,
        SerializerService $serializer,
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

        return $this->json($serializer->serializeConsultation($consultation), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(
        string $id,
        Request $request,
        MedicalConsultationRepository $repo,
        EntityManagerInterface $em,
        AnimalRepository $animalRepo,
        UserRepository $userRepo,
        SerializerService $serializer,
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

        if (!$this->memeClinic($consultation)) {
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

        return $this->json($serializer->serializeConsultation($consultation));
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

        if (!$this->memeClinic($consultation)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $em->remove($consultation);
        $em->flush();

        return $this->json(null, 204);
    }

}
