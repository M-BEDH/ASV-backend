<?php

namespace App\Controller\Api;

use App\Constant\RoleConstants;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\SerializerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

// Schéma de réponse partagé (inline, répété à chaque endpoint) : reflète SerializerService::serializeUser()
#[OA\Tag(name: 'Users')]
#[Route('/api/users')]
final class UserApiController extends AbstractController
{
    use ClinicAccessTrait;

    #[OA\Get(
        summary: 'Liste le staff de la clinique de l\'utilisateur connecté (interdit pour un client)',
        tags: ['Users'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste du staff',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'email', type: 'string'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'role', type: 'string'),
                    new OA\Property(property: 'isVet', type: 'boolean'),
                    new OA\Property(property: 'pending', type: 'boolean', description: 'true si le compte n\'est pas encore activé (password null)'),
                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                ]))
            ),
            new OA\Response(response: 403, description: 'Accès refusé (client)'),
        ]
    )]
    #[Route('', methods: ['GET'])]
    public function index(UserRepository $repo, SerializerService $serializer): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();

        // Un client n'a pas de clinic_id (il passe par user_clinic ManyToMany) :
        // getClinic() retourne null → la requête tomberait sur findBy(['clinic' => null])
        // et exposerait les comptes staff sans clinique assignée.
        if ($me->getRole() === RoleConstants::CLIENT) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $clinic = $me->getClinic();

        $users = $clinic
            ? $repo->findByClinic($clinic)
            : $repo->findBy(['clinic' => null]);

        return $this->json(array_map($serializer->serializeUser(...), $users));
    }

    #[OA\Get(
        summary: 'Récupère un membre du staff (même clinique uniquement, interdit pour un client)',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Membre du staff',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'email', type: 'string'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'role', type: 'string'),
                    new OA\Property(property: 'isVet', type: 'boolean'),
                    new OA\Property(property: 'pending', type: 'boolean'),
                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                ])
            ),
            new OA\Response(response: 403, description: 'Accès refusé (client, ou clinique différente)'),
        ]
    )]
    #[Route('/{id}', methods: ['GET'])]
    public function show(User $user, SerializerService $serializer): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();

        // Même raison que index() : getClinic() null pour un client → null === null dans memeClinic()
        // permettrait de voir un user sans clinique.
        if ($me->getRole() === RoleConstants::CLIENT) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        // Vérifie que l'utilisateur appartient à la même clinique que l'utilisateur connecté
        if (!$this->memeClinic($user)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        return $this->json($serializer->serializeUser($user));
    }

    #[OA\Post(
        summary: 'Crée un pré-compte collaborateur dans la clinique du responsable connecté (réservé au rôle responsable)',
        tags: ['Users'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'role', type: 'string', description: 'Une valeur de RoleConstants::ASSIGNABLE_BY_RESPONSABLE'),
            ])
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Pré-compte créé (sans mot de passe, activable via /api/auth/register)',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'email', type: 'string'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'role', type: 'string'),
                    new OA\Property(property: 'isVet', type: 'boolean'),
                    new OA\Property(property: 'pending', type: 'boolean'),
                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                ])
            ),
            new OA\Response(response: 400, description: 'Champs manquants, email invalide ou rôle non autorisé'),
            new OA\Response(response: 403, description: 'Accès refusé (pas responsable)'),
            new OA\Response(response: 409, description: 'Un compte existe déjà pour cet email dans cet établissement'),
        ]
    )]
    #[Route('', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, SerializerService $serializer, UserRepository $repo): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();

        if ($me->getRole() !== RoleConstants::RESPONSABLE) {
            return $this->json(['error' => 'Seul un responsable peut ajouter des collaborateurs.'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (empty($data['email']) || empty($data['name']) || empty($data['role'])) {
            return $this->json(['error' => 'Les champs email, name et role sont obligatoires.'], 400);
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => "Format d'email invalide."], 400);
        }
        if (!in_array($data['role'], RoleConstants::ASSIGNABLE_BY_RESPONSABLE, true)) {
            return $this->json(['error' => 'Rôle invalide. Valeurs acceptées : ' . implode(', ', RoleConstants::ASSIGNABLE_BY_RESPONSABLE) . '.'], 400);
        }

        $clinic = $me->getClinic();

        if ($repo->findOneBy(['email' => $data['email'], 'clinic' => $clinic])) {
            return $this->json(['error' => 'Un compte existe déjà pour cet email dans cet établissement.'], 409);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setName($data['name']);
        $user->setRole($data['role']);
        $user->setIsVet($data['role'] === 'veterinaire');
        $user->setClinic($clinic);
        // Pas de mot de passe : le compte sera activé lors de la première connexion du collaborateur

        $em->persist($user);
        $em->flush();

        return $this->json($serializer->serializeUser($user), 201);
    }

    #[OA\Put(
        summary: 'Modifie un membre du staff (même clinique). Changer le rôle est réservé au responsable',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'role', type: 'string', description: 'Réservé au responsable, une valeur de RoleConstants::ASSIGNABLE_BY_RESPONSABLE'),
            ])
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Membre du staff mis à jour',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'email', type: 'string'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'role', type: 'string'),
                    new OA\Property(property: 'isVet', type: 'boolean'),
                    new OA\Property(property: 'pending', type: 'boolean'),
                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                ])
            ),
            new OA\Response(response: 400, description: 'Email invalide ou rôle non autorisé'),
            new OA\Response(response: 403, description: 'Accès refusé (clinique différente, ou changement de rôle par un non-responsable)'),
        ]
    )]
    #[Route('/{id}', methods: ['PUT'])]
    public function update(Request $request, User $user, EntityManagerInterface $em, SerializerService $serializer): JsonResponse
    {
        // Vérifie que l'utilisateur à modifier appartient à la même clinique que l'utilisateur connecté
        if (!$this->memeClinic($user)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['role'])) {
            /** @var User $me */
            $me = $this->getUser();
            if ($me->getRole() !== RoleConstants::RESPONSABLE) {
                return $this->json(['error' => 'Seul un responsable peut modifier le rôle d\'un collaborateur.'], 403);
            }
            if (!in_array($data['role'], RoleConstants::ASSIGNABLE_BY_RESPONSABLE, true)) {
                return $this->json(['error' => 'Rôle invalide. Valeurs acceptées : ' . implode(', ', RoleConstants::ASSIGNABLE_BY_RESPONSABLE) . '.'], 400);
            }
            $user->setRole($data['role']);
            $user->setIsVet($data['role'] === 'veterinaire');
        }

        if (isset($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return $this->json(['error' => "Format d'email invalide."], 400);
            }
            $user->setEmail($data['email']);
        }
        if (isset($data['name'])) {
            $user->setName($data['name']);
        }

        $em->flush();

        return $this->json($serializer->serializeUser($user));
    }

    #[OA\Delete(
        summary: 'Anonymise un membre du staff (soft delete) — rôles autorisés : cf. RoleConstants::CAN_DELETE_USER',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Anonymisé'),
            new OA\Response(response: 403, description: 'Accès refusé (rôle non autorisé, ou clinique différente)'),
        ]
    )]
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(User $user, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();

        if (!in_array($me->getRole(), RoleConstants::CAN_DELETE_USER, true)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        // Vérifie que l'utilisateur à supprimer appartient à la même clinique que l'utilisateur connecté
        if (!$this->memeClinic($user)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $user->anonymize();
        $em->flush();

        return $this->json(null, 204);
    }

}
