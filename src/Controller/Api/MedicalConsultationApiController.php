<?php

namespace App\Controller\Api;

use App\Constant\RoleConstants;
use App\Entity\MedicalConsultation;
use App\Entity\User;
use App\Repository\AnimalRepository;
use App\Repository\MedicalConsultationRepository;
use App\Repository\OwnerRepository;
use App\Repository\UserRepository;
use App\Security\Voter\MedicalConsultationVoter;
use App\Service\SerializerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Consultations')]
#[Route('/api/consultations')]
final class MedicalConsultationApiController extends AbstractController
{
    #[OA\Get(
        summary: 'Liste les consultations (client : celles de ses animaux, toutes cliniques ; staff : celles de sa clinique)',
        tags: ['Consultations'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des consultations',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'dateConsultation', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(property: 'motif', type: 'string'),
                    new OA\Property(property: 'compteRendu', type: 'string', nullable: true),
                    new OA\Property(property: 'traitements', type: 'string', nullable: true),
                    new OA\Property(property: 'clinicId', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'animal', type: 'object', nullable: true, properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'nom', type: 'string'),
                        new OA\Property(property: 'espece', type: 'string'),
                    ]),
                    new OA\Property(property: 'veterinaire', type: 'object', nullable: true, properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'name', type: 'string'),
                    ]),
                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                ]))
            ),
        ]
    )]
    #[Route('', methods: ['GET'])]
    public function index(MedicalConsultationRepository $repo, OwnerRepository $ownerRepo, SerializerService $serializer): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();

        if ($me->getRole() === RoleConstants::CLIENT) {
            // Un client peut être propriétaire dans plusieurs cliniques → on récupère tous ses owners
            $owners = $ownerRepo->findBy(['user' => $me]);

            // Aucun owner trouvé → le client n'a aucun animal nulle part, liste vide sans requête inutile
            if ($owners === []) {
                return $this->json([]);
            }

            // Extrait les IDs : array_map récupère les IDs, array_filter retire les null, array_values réindexe
            $ownerIds = array_values(array_filter(array_map(fn($o) => $o->getId(), $owners)));

            // Récupère toutes les consultations liées aux animaux de ces owners en une seule requête SQL
            $consultations = $repo->findByOwnersWithRelations($ownerIds);

            return $this->json(array_map(fn($c) => $serializer->serializeConsultation($c), $consultations));
        }

        $clinic = $me->getClinic();
        if (!$clinic) return $this->json([]);
        $consultations = $repo->findByClinic($clinic);

        return $this->json(array_map(fn($c) => $serializer->serializeConsultation($c), $consultations));
    }

    #[OA\Get(
        summary: 'Récupère une consultation (MedicalConsultationVoter::VIEW — mêmes règles que l\'animal concerné)',
        tags: ['Consultations'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Consultation',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'dateConsultation', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(property: 'motif', type: 'string'),
                    new OA\Property(property: 'compteRendu', type: 'string', nullable: true),
                    new OA\Property(property: 'traitements', type: 'string', nullable: true),
                ])
            ),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Consultation introuvable'),
        ]
    )]
    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id, MedicalConsultationRepository $repo, SerializerService $serializer): JsonResponse
    {
        $consultation = $repo->find($id);
        if (!$consultation) {
            return $this->json(['error' => 'Consultation introuvable.'], 404);
        }

        if (!$this->isGranted(MedicalConsultationVoter::VIEW, $consultation)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        return $this->json($serializer->serializeConsultation($consultation));
    }

    #[OA\Post(
        summary: 'Crée une consultation médicale (MedicalConsultationVoter::CREATE — réservé véto/assistant/responsable, jamais client ni bénévole)',
        tags: ['Consultations'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'animalId', type: 'string', format: 'uuid'),
                new OA\Property(property: 'dateConsultation', type: 'string', format: 'date-time'),
                new OA\Property(property: 'motif', type: 'string'),
                new OA\Property(property: 'compteRendu', type: 'string', nullable: true),
                new OA\Property(property: 'traitements', type: 'string', nullable: true),
                new OA\Property(property: 'veterinaireId', type: 'string', format: 'uuid', nullable: true, description: 'Par défaut, l\'utilisateur connecté. Doit être vétérinaire, même clinique'),
            ])
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Consultation créée',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'motif', type: 'string'),
                    new OA\Property(property: 'dateConsultation', type: 'string', format: 'date-time', nullable: true),
                ])
            ),
            new OA\Response(response: 400, description: 'Champs manquants, ou vétérinaire invalide/hors clinique'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Animal ou vétérinaire introuvable'),
        ]
    )]
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

        if (!$this->isGranted(MedicalConsultationVoter::CREATE)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (empty($data['animalId']) || empty($data['dateConsultation']) || empty($data['motif'])) {
            return $this->json(['error' => 'Les champs animalId, dateConsultation et motif sont obligatoires.'], 400);
        }

        try {
            $dateConsultation = new \DateTime($data['dateConsultation']);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Format de date invalide.'], 400);
        }

        $animal = $animalRepo->find($data['animalId']);
        if (!$animal) {
            return $this->json(['error' => 'Animal introuvable.'], 404);
        }

        $consultation = new MedicalConsultation();
        $consultation->setAnimal($animal);
        $consultation->setMotif($data['motif']);
        $consultation->setDateConsultation($dateConsultation);
        $consultation->setCompteRendu($data['compteRendu'] ?? null);
        $consultation->setTraitements($data['traitements'] ?? null);
        $consultation->setClinic($me->getClinic());

        // Default to the authenticated user as veterinaire; allow override / Par défaut, l'utilisateur authentifié est veterinaire ; Autoriser la dérogation
        $vetId = $data['veterinaireId'] ?? null;
        if ($vetId) {
            $vet = $userRepo->find($vetId);
            if (!$vet) {
                return $this->json(['error' => 'Vétérinaire introuvable.'], 404);
            }
            if ($vet->getClinic()?->getId() !== $me->getClinic()?->getId()) {
                return $this->json(['error' => 'Le vétérinaire doit appartenir au même établissement.'], 400);
            }
            if (!$vet->isVet()) {
                return $this->json(['error' => 'Le praticien sélectionné doit être vétérinaire.'], 400);
            }
            $consultation->setVeterinaire($vet);
        } else {
            $consultation->setVeterinaire($me);
        }

        $em->persist($consultation);
        $em->flush();

        return $this->json($serializer->serializeConsultation($consultation), 201);
    }

    #[OA\Put(
        summary: 'Modifie une consultation médicale (MedicalConsultationVoter::EDIT — canWrite() + même clinique)',
        tags: ['Consultations'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'motif', type: 'string'),
                new OA\Property(property: 'dateConsultation', type: 'string', format: 'date-time'),
                new OA\Property(property: 'compteRendu', type: 'string', nullable: true),
                new OA\Property(property: 'traitements', type: 'string', nullable: true),
                new OA\Property(property: 'animalId', type: 'string', format: 'uuid', nullable: true),
                new OA\Property(property: 'veterinaireId', type: 'string', format: 'uuid', nullable: true),
            ])
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Consultation mise à jour',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'motif', type: 'string'),
                ])
            ),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Consultation, animal ou vétérinaire introuvable'),
        ]
    )]
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

        if (!$this->isGranted(MedicalConsultationVoter::EDIT, $consultation)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['motif'])) {
            $consultation->setMotif($data['motif']);
        }
        if (isset($data['dateConsultation'])) {
            try {
                $consultation->setDateConsultation(new \DateTime($data['dateConsultation']));
            } catch (\Exception $e) {
                return $this->json(['error' => 'Format de date invalide.'], 400);
            }
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

        // array_key_et non isset pour détecter aussi le cas null explicite (isset retournerait false si la valeur est null).
        if (array_key_exists('veterinaireId', $data)) {
            if ($data['veterinaireId'] === null) {
                $consultation->setVeterinaire(null);
            } else {
                $vet = $userRepo->find($data['veterinaireId']);
                if (!$vet) {
                    return $this->json(['error' => 'Vétérinaire introuvable.'], 404);
                }
                if ($vet->getClinic()?->getId() !== $consultation->getClinic()?->getId()) {
                    return $this->json(['error' => 'Le vétérinaire doit appartenir au même établissement.'], 400);
                }
                if (!$vet->isVet()) {
                    return $this->json(['error' => 'Le praticien sélectionné doit être vétérinaire.'], 400);
                }
                $consultation->setVeterinaire($vet);
            }
        }

        $em->flush();

        return $this->json($serializer->serializeConsultation($consultation));
    }

    #[OA\Delete(
        summary: 'Supprime une consultation médicale (MedicalConsultationVoter::DELETE — canWrite() + même clinique)',
        tags: ['Consultations'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Supprimée'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Consultation introuvable'),
        ]
    )]
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id, MedicalConsultationRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $consultation = $repo->find($id);
        if (!$consultation) {
            return $this->json(['error' => 'Consultation introuvable.'], 404);
        }

        if (!$this->isGranted(MedicalConsultationVoter::DELETE, $consultation)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $em->remove($consultation);
        $em->flush();

        return $this->json(null, 204);
    }

}
