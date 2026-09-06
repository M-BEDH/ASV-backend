<?php

namespace App\Controller\Api;

use App\Constant\RoleConstants;
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
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Owners')]
#[Route('/api/owners')]
final class OwnerApiController extends AbstractController
{
    use ClinicAccessTrait;

    #[OA\Get(
        summary: 'Liste les propriétaires (client : ses propres fiches ; staff : ceux de sa clinique, scopé côté serveur)',
        tags: ['Owners'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des propriétaires',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'nom', type: 'string'),
                    new OA\Property(property: 'prenom', type: 'string'),
                    new OA\Property(property: 'adresse', type: 'string', nullable: true),
                    new OA\Property(property: 'telephone', type: 'string', nullable: true),
                    new OA\Property(property: 'email', type: 'string'),
                    new OA\Property(property: 'clinicIds', type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), description: 'Jamais un refuge/association, seulement de vraies cliniques'),
                    new OA\Property(property: 'createdBy', type: 'object', nullable: true, properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'name', type: 'string'),
                    ]),
                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                ]))
            ),
        ]
    )]
    #[Route('', methods: ['GET'])]
    public function index(OwnerRepository $repo, SerializerService $serializer): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();

        if ($me->getRole() === RoleConstants::CLIENT) {
            $owners = $repo->findBy(['user' => $me]);
            return $this->json(array_map($serializer->serializeOwner(...), $owners));
        }

        $clinic = $me->getClinic();
        $owners = $clinic ? $repo->findByClinic($clinic) : [];

        return $this->json(array_map($serializer->serializeOwner(...), $owners));
    }

    #[OA\Get(
        summary: 'Récupère un propriétaire (client : soi-même ; staff : au moins une clinique partagée)',
        tags: ['Owners'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Propriétaire',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'nom', type: 'string'),
                    new OA\Property(property: 'prenom', type: 'string'),
                    new OA\Property(property: 'email', type: 'string'),
                    new OA\Property(property: 'clinicIds', type: 'array', items: new OA\Items(type: 'string', format: 'uuid')),
                ])
            ),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Propriétaire introuvable'),
        ]
    )]
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

    #[OA\Post(
        summary: 'Crée un propriétaire (ou le rattache s\'il existe déjà par email). Jamais rattaché si la clinique créatrice est un refuge/association',
        tags: ['Owners'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'nom', type: 'string'),
                new OA\Property(property: 'prenom', type: 'string'),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'adresse', type: 'string', nullable: true),
                new OA\Property(property: 'telephone', type: 'string', nullable: true),
            ])
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Propriétaire déjà existant, rattaché à la clinique le cas échéant',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'nom', type: 'string'),
                    new OA\Property(property: 'prenom', type: 'string'),
                    new OA\Property(property: 'clinicIds', type: 'array', items: new OA\Items(type: 'string', format: 'uuid')),
                ])
            ),
            new OA\Response(
                response: 201,
                description: 'Nouveau propriétaire créé',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'nom', type: 'string'),
                    new OA\Property(property: 'prenom', type: 'string'),
                    new OA\Property(property: 'clinicIds', type: 'array', items: new OA\Items(type: 'string', format: 'uuid')),
                ])
            ),
            new OA\Response(response: 400, description: 'Champs manquants/invalides, ou doublon email dans le même établissement'),
            new OA\Response(response: 403, description: 'Accès refusé (client ou bénévole)'),
        ]
    )]
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

        if ($me->getRole() === RoleConstants::BENEVOLE) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        if ($me->getRole() === RoleConstants::CLIENT) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        // Staff (véto, responsable, assistant...)
        $clinic = $me->getClinic();
        // Un Owner ne doit jamais être rattaché à un refuge/association, seulement à une vraie clinique
        if ($clinic && $clinic->getType() !== 'clinique') {
            $clinic = null;
        }

        // Si un Owner avec cet email existe déjà ET a déjà une vraie clinique → on ajoute juste la nouvelle clinique.
        // Sinon (email inconnu, ou trouvé mais simple trace d'adoption en refuge sans clinique) → nouvel Owner,
        // pour ne jamais faire hériter une clinique du passif d'un refuge.
        $existing = $ownerRepo->findOneBy(['email' => $data['email']]);
        if ($existing !== null && $existing->getClinics()->count() > 0) {
            if ($clinic && !$existing->hasClinic($clinic)) {
                $existing->addClinic($clinic);
            }

            $linkedUser = $existing->getUser();
            // Pas de précompte tant qu'aucune vraie clinique n'est impliquée (ex. refuge : simple trace d'adoption)
            if (!$linkedUser && $clinic) {
                $linkedUser = $userRepo->findOneBy(['email' => $data['email']]);
                if (!$linkedUser) {
                    $linkedUser = new User();
                    $linkedUser->setEmail($data['email']);
                    $linkedUser->setName(trim(" {$existing->getNom()}"));
                    $linkedUser->setRole(RoleConstants::CLIENT);
                    $em->persist($linkedUser);
                }
                $existing->setUser($linkedUser);
            }

            if ($linkedUser && $linkedUser->getRole() === RoleConstants::CLIENT && $clinic && !$linkedUser->hasClinic($clinic)) {
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
        // Pas de précompte tant qu'aucune vraie clinique n'est impliquée (ex. refuge : simple trace d'adoption)
        $existingUser = $userRepo->findOneBy(['email' => $data['email']]);
        if (!$existingUser && $clinic) {
            $clientUser = new User();
            $clientUser->setEmail($data['email']);
            $clientUser->setName(trim($data['prenom'] . ' ' . $data['nom']));
            $clientUser->setRole(RoleConstants::CLIENT);
            $clientUser->addClinic($clinic);
            $owner->setUser($clientUser);
            $em->persist($clientUser);
            $em->flush();
        } elseif ($existingUser !== null && $existingUser->getRole() === RoleConstants::CLIENT) {
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

        if ($me->getRole() === RoleConstants::CLIENT) {
            return $owner->getUser()?->getId() === $me->getId();
        }

        // Vérifie que le propriétaire partage au moins une clinique avec l'utilisateur connecté
        return $this->hasSharedClinic($owner);
    }

    #[OA\Put(
        summary: 'Modifie un propriétaire (interdit pour le bénévole)',
        tags: ['Owners'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'nom', type: 'string'),
                new OA\Property(property: 'prenom', type: 'string'),
                new OA\Property(property: 'adresse', type: 'string', nullable: true),
                new OA\Property(property: 'telephone', type: 'string', nullable: true),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
            ])
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Propriétaire mis à jour',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'nom', type: 'string'),
                    new OA\Property(property: 'prenom', type: 'string'),
                ])
            ),
            new OA\Response(response: 400, description: 'Email invalide ou déjà utilisé'),
            new OA\Response(response: 403, description: 'Accès refusé (bénévole, ou pas de clinique partagée)'),
            new OA\Response(response: 404, description: 'Propriétaire introuvable'),
        ]
    )]
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

        if ($me->getRole() === RoleConstants::BENEVOLE) {
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
            if ($linkedUser && $linkedUser->getRole() === RoleConstants::CLIENT) {
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
            if ($me->getRole() === RoleConstants::CLIENT && $data['email'] !== $me->getEmail()) {
                $me->setEmail($data['email']);
            }
        }

        $em->flush();

        return $this->json($serializer->serializeOwner($owner));
    }

    #[OA\Delete(
        summary: 'Anonymise un propriétaire (soft delete), et le User client lié le cas échéant (interdit pour le bénévole)',
        tags: ['Owners'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Anonymisé'),
            new OA\Response(response: 403, description: 'Accès refusé (bénévole, ou pas de clinique partagée)'),
            new OA\Response(response: 404, description: 'Propriétaire introuvable'),
        ]
    )]
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

        if ($me->getRole() === RoleConstants::BENEVOLE) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        // anonymisation 
        $linkedUser = $owner->getUser();
        if ($linkedUser && $linkedUser->getRole() === RoleConstants::CLIENT) {
            $linkedUser->anonymize();
        }

        $owner->anonymize();
        $em->flush();

        return $this->json(null, 204);
    }
}
