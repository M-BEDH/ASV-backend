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

#[Route('/api/clinics')]
final class ClinicApiController extends AbstractController
{
    // Public: list clinics (for registration dropdown)
    #[Route('', methods: ['GET'])]
    public function list(ClinicRepository $repo, SerializerService $serializer): JsonResponse
    {
        $clinics = $repo->findBy([], ['name' => 'ASC']);

        return $this->json(array_map(fn(Clinic $c) => $serializer->serializeClinic($c), $clinics));
    }

    // Public: trouve les cliniques où cet email est owner (pour l'inscription client)
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
