<?php

namespace App\Controller\Api;

use App\Entity\Clinic;
use App\Entity\User;
use App\Repository\ClinicRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/clinics')]
final class ClinicApiController extends AbstractController
{
    // Public: list clinics (for registration dropdown)
    #[Route('', methods: ['GET'])]
    public function list(ClinicRepository $repo): JsonResponse
    {
        $clinics = $repo->findBy([], ['name' => 'ASC']);

        return $this->json(array_map(fn(Clinic $c) => [
            'id'        => $c->getId(),
            'name'      => $c->getName(),
            'type'      => $c->getType(),
            'createdAt' => $c->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $clinics));
    }

    // Authenticated: get a single clinic
    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id, ClinicRepository $repo): JsonResponse
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

        return $this->json([
            'id'        => $clinic->getId(),
            'name'      => $clinic->getName(),
            'type'      => $clinic->getType(),
            'createdAt' => $clinic->getCreatedAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    // Authenticated (vet/assistant): update clinic name
    #[Route('/{id}', methods: ['PUT'])]
    public function update(string $id, Request $request, ClinicRepository $repo, EntityManagerInterface $em): JsonResponse
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

        $data = json_decode($request->getContent(), true);
        if (!empty($data['name'])) {
            $clinic->setName($data['name']);
        }

        $em->flush();

        return $this->json([
            'id'        => $clinic->getId(),
            'name'      => $clinic->getName(),
            'type'      => $clinic->getType(),
            'createdAt' => $clinic->getCreatedAt()?->format('Y-m-d H:i:s'),
        ]);
    }
}
