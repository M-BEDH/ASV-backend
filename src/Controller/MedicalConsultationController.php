<?php

namespace App\Controller;

use App\Entity\MedicalConsultation;
use App\Form\MedicalConsultationType;
use App\Repository\MedicalConsultationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/medical/consultation')]
final class MedicalConsultationController extends AbstractController
{
    #[Route(name: 'app_medical_consultation_index', methods: ['GET'])]
    public function index(MedicalConsultationRepository $medicalConsultationRepository): Response
    {
        return $this->render('medical_consultation/index.html.twig', [
            'medical_consultations' => $medicalConsultationRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_medical_consultation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $medicalConsultation = new MedicalConsultation();
        $form = $this->createForm(MedicalConsultationType::class, $medicalConsultation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($medicalConsultation);
            $entityManager->flush();

            return $this->redirectToRoute('app_medical_consultation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('medical_consultation/new.html.twig', [
            'medical_consultation' => $medicalConsultation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_medical_consultation_show', methods: ['GET'])]
    public function show(MedicalConsultation $medicalConsultation): Response
    {
        return $this->render('medical_consultation/show.html.twig', [
            'medical_consultation' => $medicalConsultation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_medical_consultation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, MedicalConsultation $medicalConsultation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(MedicalConsultationType::class, $medicalConsultation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_medical_consultation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('medical_consultation/edit.html.twig', [
            'medical_consultation' => $medicalConsultation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_medical_consultation_delete', methods: ['POST'])]
    public function delete(Request $request, MedicalConsultation $medicalConsultation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$medicalConsultation->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($medicalConsultation);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_medical_consultation_index', [], Response::HTTP_SEE_OTHER);
    }
}
