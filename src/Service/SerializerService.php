<?php

namespace App\Service;

use App\Entity\Animal;
use App\Entity\Clinic;
use App\Entity\MedicalConsultation;
use App\Entity\Owner;
use App\Entity\User;

class SerializerService
{
    public function serializeConsultation(MedicalConsultation $c): array
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

    public function serializeAnimal(Animal $a): array
    {
        return [
            'id' => $a->getId(),
            'nom' => $a->getNom(),
            'espece' => $a->getEspece(),
            'race' => $a->getRace(),
            'dateNaissance' => $a->getDateNaissance()?->format('Y-m-d'),
            'remarques' => $a->getRemarques(),
            'proprietaire' => $a->getProprietaire() ? [
                'id' => $a->getProprietaire()->getId(),
                'nom' => $a->getProprietaire()->getNom(),
                'prenom' => $a->getProprietaire()->getPrenom(),
            ] : null,
            'createdBy' => $a->getCreatedBy() ? [
                'id' => $a->getCreatedBy()->getId(),
                'name' => $a->getCreatedBy()->getName(),
            ] : null,
            'clinicId' => $a->getClinic()?->getId(),
            'createdAt' => $a->getCreatedAt()?->format('c'),
        ];
    }

    public function serializeOwner(Owner $o): array
    {
        return [
            'id' => $o->getId(),
            'nom' => $o->getNom(),
            'prenom' => $o->getPrenom(),
            'adresse' => $o->getAdresse(),
            'telephone' => $o->getTelephone(),
            'email' => $o->getEmail(),
            'clinicIds' => $o->getClinics()->map(fn($c) => $c->getId())->toArray(),
            'createdBy' => $o->getCreatedBy() ? [
                'id' => $o->getCreatedBy()->getId(),
                'name' => $o->getCreatedBy()->getName(),
            ] : null,
            'createdAt' => $o->getCreatedAt()?->format('c'),
        ];
    }

    public function serializeUser(User $u): array
    {
        return [
            'id' => $u->getId(),
            'email' => $u->getEmail(),
            'name' => $u->getName(),
            'role' => $u->getRole(),
            'createdAt' => $u->getCreatedAt()?->format('c'),
        ];
    }

    //Prometheus : sérialise les données d'un utilisateur pour l'enregistrement et l'affichage dans Grafana
    public function serializeRegisterResponseUser(User $u): array
    {
        return [
            'id' => $u->getId(),
            'email' => $u->getEmail(),
            'name' => $u->getName(),
            'role' => $u->getRole(),
            'clinicId' => $u->getClinic()?->getId(),
        ];
    }

    // Prometheus : sérialise les données d'un utilisateur pour la réponse de connexion et l'affichage dans Grafana
    public function serializeLoginResponseUser(User $u): array
    {
        if ($u->getRole() === 'client') {
            return [
                'id'       => $u->getId(),
                'email'    => $u->getEmail(),
                'name'     => $u->getName(),
                'role'     => $u->getRole(),
                'clinicIds' => $u->getClinics()->map(fn($c) => $c->getId())->toArray(),
            ];
        }

        return [
            'id'         => $u->getId(),
            'email'      => $u->getEmail(),
            'name'       => $u->getName(),
            'role'       => $u->getRole(),
            'clinicId'   => $u->getClinic()?->getId(),
            'clinicName' => $u->getClinic()?->getName(),
        ];
    }

    // Prometheus : sérialise les données d'un utilisateur pour la réponse de connexion réussie et l'affichage dans Grafana
    public function serializeLoginSuccessResponse(User $u, string $token): array
    {
        return [
            'token' => $token,
            'user' => $this->serializeLoginResponseUser($u),
        ];
    }

    
    public function serializeClinic(Clinic $c): array
    {
        return [
            'id' => $c->getId(),
            'name' => $c->getName(),
            'type' => $c->getType(),
            'createdAt' => $c->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
