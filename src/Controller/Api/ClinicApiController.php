<?php

namespace App\Controller\Api;

use App\Constant\RoleConstants;
use App\Entity\Clinic;
use App\Entity\User;
use App\Repository\ClinicRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\OwnerRepository;
use App\Service\SerializerService;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Clinics')]
#[Route('/api/clinics')]
final class ClinicApiController extends AbstractController
{
    // Public: list clinics (for registration dropdown)
    #[OA\Get(
        summary: 'Liste toutes les cliniques (accès public, utilisé par le dropdown d\'inscription)',
        tags: ['Clinics'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des cliniques, triée par nom',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'type', type: 'string', enum: ['clinique', 'refuge', 'association']),
                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                ]))
            ),
        ]
    )]
    #[Route('', methods: ['GET'])]
    public function list(ClinicRepository $repo, SerializerService $serializer): JsonResponse
    {
        $clinics = $repo->findBy([], ['name' => 'ASC']);

        return $this->json(array_map(fn(Clinic $c) => $serializer->serializeClinic($c), $clinics));
    }

    // Public: trouve les cliniques où cet email est owner (pour l'inscription client)
    #[OA\Get(
        summary: 'Cherche les cliniques où un email est déjà propriétaire (accès public, utilisé par l\'inscription client)',
        tags: ['Clinics'],
        parameters: [
            new OA\Parameter(name: 'email', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'email')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Résultat de la recherche par email',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'found', type: 'boolean'),
                    new OA\Property(property: 'clinics', type: 'array', items: new OA\Items(properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'type', type: 'string', enum: ['clinique', 'refuge', 'association']),
                    ])),
                ])
            ),
            new OA\Response(response: 400, description: 'Email invalide ou manquant'),
        ]
    )]
    #[Route('/by-email', methods: ['GET'])]
    public function byEmail(Request $request, OwnerRepository $ownerRepo): JsonResponse
    {
        $email = $request->query->get('email');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => "Email invalide."], 400);
        }

        $owner = $ownerRepo->findOneBy(['email' => $email]);

        if (!$owner) {
            return $this->json(['found' => false, 'clinics' => []]);
        }

        $clinics = [];
        foreach ($owner->getClinics() as $clinic) {
            $clinics[] = [
                'id' => $clinic->getId(),
                'name' => $clinic->getName(),
                'type' => $clinic->getType(),
            ];
        }

        return $this->json(['found' => true, 'clinics' => $clinics]);
    }

    // Authenticated: get a single clinic
    #[OA\Get(
        summary: 'Récupère une clinique (uniquement la clinique de l\'utilisateur connecté)',
        tags: ['Clinics'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Clinique trouvée', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'type', type: 'string', enum: ['clinique', 'refuge', 'association']),
                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
            ])),
            new OA\Response(response: 403, description: 'Accès refusé (clinique différente de celle de l\'utilisateur)'),
            new OA\Response(response: 404, description: 'Clinique introuvable'),
        ]
    )]
    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id, ClinicRepository $repo, SerializerService $serializer): JsonResponse
    {
        $clinic = $repo->find($id);
        if (!$clinic) {
            return $this->json(['error' => 'Clinique introuvable.'], 404);
        }

        /** @var User $me */
        $me = $this->getUser();
        if ($me->getClinic()?->getId() !== $clinic->getId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        return $this->json($serializer->serializeClinic($clinic));
    }

    // Authenticated (vet/assistant): update clinic name
    #[OA\Put(
        summary: 'Modifie le nom d\'une clinique (rôles autorisés : cf. RoleConstants::CAN_EDIT_CLINIC)',
        tags: ['Clinics'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'name', type: 'string'),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Clinique mise à jour', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'type', type: 'string', enum: ['clinique', 'refuge', 'association']),
                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
            ])),
            new OA\Response(response: 403, description: 'Accès refusé (mauvaise clinique ou rôle non autorisé)'),
            new OA\Response(response: 404, description: 'Clinique introuvable'),
        ]
    )]
    #[Route('/{id}', methods: ['PUT'])]
    public function update(string $id, Request $request, ClinicRepository $repo, EntityManagerInterface $em, SerializerService $serializer): JsonResponse
    {
        $clinic = $repo->find($id);
        if (!$clinic) {
            return $this->json(['error' => 'Clinique introuvable.'], 404);
        }

        /** @var User $me */
        $me = $this->getUser();
        if ($me->getClinic()?->getId() !== $clinic->getId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        if (!in_array($me->getRole(), RoleConstants::CAN_EDIT_CLINIC, true)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (!empty($data['name'])) {
            $clinic->setName($data['name']);
        }

        $em->flush();

        return $this->json($serializer->serializeClinic($clinic));
    }
}
