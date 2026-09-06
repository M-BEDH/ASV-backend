<?php

namespace App\Controller\Api;

use App\Constant\RoleConstants;
use App\Entity\Animal;
use App\Entity\User;
use App\Repository\AnimalRepository;
use App\Repository\MedicalConsultationRepository;
use App\Repository\OwnerRepository;
use App\Service\SerializerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Animals')]
#[Route('/api/animals')]
final class AnimalApiController extends AbstractController
{
    use ClinicAccessTrait;

    #[OA\Get(
        summary: 'Liste les animaux (client : ses propres animaux toutes cliniques confondues ; staff : ceux de sa clinique)',
        tags: ['Animals'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des animaux',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'nom', type: 'string'),
                    new OA\Property(property: 'espece', type: 'string'),
                    new OA\Property(property: 'race', type: 'string', nullable: true),
                    new OA\Property(property: 'dateNaissance', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'remarques', type: 'string', nullable: true),
                    new OA\Property(property: 'proprietaire', type: 'object', nullable: true, properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'nom', type: 'string'),
                        new OA\Property(property: 'prenom', type: 'string'),
                    ]),
                    new OA\Property(property: 'createdBy', type: 'object', nullable: true, properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'name', type: 'string'),
                    ]),
                    new OA\Property(property: 'clinicId', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                ]))
            ),
        ]
    )]
    #[Route('', methods: ['GET'])]
    public function index(AnimalRepository $repo, OwnerRepository $ownerRepo, SerializerService $serializer): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();

        if ($me->getRole() === RoleConstants::CLIENT) {
            $owner = $ownerRepo->findOneBy(['email' => $me->getEmail()]);
            if (!$owner) {
                return $this->json([]);
            }
            $animals = $repo->findBy(['proprietaire' => $owner]);
            return $this->json(array_map($serializer->serializeAnimal(...), $animals));
        }

        $clinic = $me->getClinic();
        $animals = $clinic
            ? $repo->findByClinic($clinic)
            : $repo->findBy(['clinic' => null]);

        return $this->json(array_map($serializer->serializeAnimal(...), $animals));
    }

    #[OA\Get(
        summary: 'Récupère un animal (règles de visibilité : cf. doShowAnimal — client propriétaire, ou staff même clinique/refuge d\'origine)',
        tags: ['Animals'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Animal',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'nom', type: 'string'),
                    new OA\Property(property: 'espece', type: 'string'),
                    new OA\Property(property: 'race', type: 'string', nullable: true),
                    new OA\Property(property: 'dateNaissance', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'remarques', type: 'string', nullable: true),
                    new OA\Property(property: 'proprietaire', type: 'object', nullable: true, properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'nom', type: 'string'),
                        new OA\Property(property: 'prenom', type: 'string'),
                    ]),
                    new OA\Property(property: 'createdBy', type: 'object', nullable: true, properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'name', type: 'string'),
                    ]),
                    new OA\Property(property: 'clinicId', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                ])
            ),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Animal introuvable'),
        ]
    )]
    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id, AnimalRepository $repo, SerializerService $serializer): JsonResponse
    {
        $animal = $repo->find($id);
        if (!$animal) {
            return $this->json(['error' => 'Animal introuvable.'], 404);
        }

        if (!$this->doShowAnimal($animal)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        return $this->json($serializer->serializeAnimal($animal));
    }

    #[OA\Post(
        summary: 'Crée un animal dans la clinique de l\'utilisateur connecté (canWrite() : staff sauf client, bénévole seulement en refuge/association)',
        tags: ['Animals'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'nom', type: 'string'),
                new OA\Property(property: 'espece', type: 'string'),
                new OA\Property(property: 'race', type: 'string', nullable: true),
                new OA\Property(property: 'remarques', type: 'string', nullable: true),
                new OA\Property(property: 'dateNaissance', type: 'string', format: 'date', nullable: true),
                new OA\Property(property: 'proprietaireId', type: 'string', format: 'uuid', nullable: true),
            ])
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Animal créé',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'nom', type: 'string'),
                    new OA\Property(property: 'espece', type: 'string'),
                    new OA\Property(property: 'clinicId', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                ])
            ),
            new OA\Response(response: 400, description: 'Champs nom/espece manquants'),
            new OA\Response(response: 403, description: 'Accès refusé (canWrite)'),
            new OA\Response(response: 404, description: 'Propriétaire introuvable'),
        ]
    )]
    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        OwnerRepository $ownerRepo,
        SerializerService $serializer,
    ): JsonResponse {
        /** @var User $me */
        $me = $this->getUser();

        if (!$this->canWrite()) {
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

        return $this->json($serializer->serializeAnimal($animal), 201);
    }

    #[OA\Put(
        summary: 'Modifie un animal, y compris le rattachement au propriétaire (ex. adoption)',
        tags: ['Animals'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'nom', type: 'string'),
                new OA\Property(property: 'espece', type: 'string'),
                new OA\Property(property: 'race', type: 'string', nullable: true),
                new OA\Property(property: 'remarques', type: 'string', nullable: true),
                new OA\Property(property: 'dateNaissance', type: 'string', format: 'date', nullable: true),
                new OA\Property(property: 'proprietaireId', type: 'string', format: 'uuid', nullable: true, description: 'null pour détacher le propriétaire'),
            ])
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Animal mis à jour',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'nom', type: 'string'),
                    new OA\Property(property: 'espece', type: 'string'),
                ])
            ),
            new OA\Response(response: 403, description: 'Accès refusé (canWrite ou doShowAnimal)'),
            new OA\Response(response: 404, description: 'Animal ou propriétaire introuvable'),
        ]
    )]
    #[Route('/{id}', methods: ['PUT'])]
    public function update(
        string $id,
        Request $request,
        AnimalRepository $repo,
        EntityManagerInterface $em,
        OwnerRepository $ownerRepo,
        SerializerService $serializer,
    ): JsonResponse {
        /** @var User $me */
        $me = $this->getUser();

        if (!$this->canWrite()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $animal = $repo->find($id);
        if (!$animal) {
            return $this->json(['error' => 'Animal introuvable.'], 404);
        }

        if (!$this->doShowAnimal($animal)) {
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

        return $this->json($serializer->serializeAnimal($animal));
    }

    #[OA\Get(
        summary: 'Historique des consultations médicales d\'un animal (mêmes règles de visibilité que GET /api/animals/{id})',
        tags: ['Animals'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Historique des consultations',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'dateConsultation', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(property: 'motif', type: 'string'),
                    new OA\Property(property: 'compteRendu', type: 'string', nullable: true),
                    new OA\Property(property: 'traitements', type: 'string', nullable: true),
                    new OA\Property(property: 'veterinaire', type: 'object', nullable: true, properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'name', type: 'string'),
                    ]),
                ]))
            ),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Animal introuvable'),
        ]
    )]
    #[Route('/{id}/consultations', methods: ['GET'])]
    public function consultations(string $id, AnimalRepository $repo, MedicalConsultationRepository $consultationRepo): JsonResponse
    {
        $animal = $repo->find($id);
        if (!$animal) {
            return $this->json(['error' => 'Animal introuvable.'], 404);
        }

        if (!$this->doShowAnimal($animal)) {
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

    #[OA\Delete(
        summary: 'Supprime un animal. Si c\'était le dernier animal de son propriétaire, l\'Owner est anonymisé',
        tags: ['Animals'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Supprimé'),
            new OA\Response(response: 403, description: 'Accès refusé (canWrite ou doShowAnimal)'),
            new OA\Response(response: 404, description: 'Animal introuvable'),
        ]
    )]
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id, AnimalRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();

        if (!$this->canWrite()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $animal = $repo->find($id);
        if (!$animal) {
            return $this->json(['error' => 'Animal introuvable.'], 404);
        }

        if (!$this->doShowAnimal($animal)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $owner = $animal->getProprietaire();

        $em->remove($animal);

        // si plus d'animal alors delete de user + anonymise
        if ($owner !== null && $owner->getAnimals()->count() === 1) {
            $owner->anonymize();
        }

        $em->flush();

        return $this->json(null, 204);
    }

}
