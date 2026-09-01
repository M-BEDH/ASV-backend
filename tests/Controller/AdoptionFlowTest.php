<?php

namespace App\Tests\Controller;

use App\Constant\RoleConstants;
use App\Entity\User;

// Scénario complet : un animal recueilli en refuge est adopté, 
// puis s'inscrit plus tard comme client d'une vraie clinique vétérinaire.
// Vérifie: un Owner n'est jamais rattaché à un refuge/association,
// tout en gardant au refuge l'accès en lecture à ses propres animaux
// et à l'historique de consultations, même après adoption.
final class AdoptionFlowTest extends ApiTestCase
{
    public function testAdoptionAuRefugePuisInscriptionEnClinique(): void
    {
        $refugeVet = $this->createVet('vet-refuge@test.com', 'refuge');
        $refugeToken = $this->getToken($refugeVet->getEmail());

        $clinicVet = $this->createVet('vet-clinique@test.com');
        $clinicToken = $this->getToken($clinicVet->getEmail());

        // Le refuge recueille un animal sans propriétaire
        $animal = $this->request('POST', '/api/animals', ['nom' => 'Rex', 'espece' => 'Chien'], $refugeToken);
        self::assertResponseStatusCodeSame(201);

        // Avant adoption : le staff du refuge voit l'animal et son historique
        $this->request('GET', '/api/animals/' . $animal['id'], [], $refugeToken);
        self::assertResponseStatusCodeSame(200);
        $this->request('GET', '/api/animals/' . $animal['id'] . '/consultations', [], $refugeToken);
        self::assertResponseStatusCodeSame(200);

        // Le refuge enregistre l'adoption : l'Owner créé n'est qu'une trace de l'adoptant
        $owner = $this->request('POST', '/api/owners', [
            'nom'    => 'Dupont',
            'prenom' => 'Jean',
            'email'  => 'dupont@test.com',
        ], $refugeToken);
        self::assertResponseStatusCodeSame(201);
        self::assertSame([], $owner['clinicIds'], "L'Owner ne doit jamais être rattaché au refuge");

        // Aucun précompte client créé tant qu'aucune vraie clinique n'a enregistré cet email
        $pendingClient = $this->em->getRepository(User::class)->findOneBy(['email' => 'dupont@test.com']);
        self::assertNull($pendingClient);
        $this->request('POST', '/api/auth/register', ['email' => 'dupont@test.com', 'password' => 'Passw0rd!']);
        self::assertResponseStatusCodeSame(403);

        // Le refuge relie l'animal à son adoptant
        $this->request('PUT', '/api/animals/' . $animal['id'], ['proprietaireId' => $owner['id']], $refugeToken);
        self::assertResponseStatusCodeSame(200);

        // Après adoption : le staff du refuge garde l'accès à l'animal et à son historique
        $this->request('GET', '/api/animals/' . $animal['id'], [], $refugeToken);
        self::assertResponseStatusCodeSame(200);
        $this->request('GET', '/api/animals/' . $animal['id'] . '/consultations', [], $refugeToken);
        self::assertResponseStatusCodeSame(200);

        // La clinique enregistre un Owner avec le même email que la trace laissée par le refuge.
        // Le serveur retrouve bien cette trace via l'email, mais comme elle n'a aucune clinique
        // rattachée (clinics vide), il ne la réutilise pas : il crée un nouvel Owner pour la clinique.
        $ownerAtClinic = $this->request('POST', '/api/owners', [
            'nom'    => 'Dupont',
            'prenom' => 'Jean',
            'email'  => 'dupont@test.com',
        ], $clinicToken);
        self::assertResponseStatusCodeSame(201);
        self::assertNotSame($owner['id'], $ownerAtClinic['id'], "La clinique ne doit jamais réutiliser l'Owner créé par le refuge");
        self::assertContains($clinicVet->getClinic()->getId(), $ownerAtClinic['clinicIds']);
        self::assertNotContains($refugeVet->getClinic()->getId(), $ownerAtClinic['clinicIds'], "Le refuge ne doit jamais apparaître dans les cliniques de l'Owner");

        // Le précompte existe désormais, rattaché à la vraie clinique (pas au refuge)
        // — le flux register/login lui-même est déjà couvert par UserControllerTest
        $pendingClient = $this->em->getRepository(User::class)->findOneBy(['email' => 'dupont@test.com']);
        self::assertNotNull($pendingClient);
        self::assertSame(RoleConstants::CLIENT, $pendingClient->getRole());
        self::assertNull($pendingClient->getPassword());
        // Comparaison par ID (string), pas par objet : les requêtes HTTP ci-dessus redémarrent
        // le kernel Symfony, donc l'app tourne ensuite avec un nouvel EntityManager. $pendingClient
        // est chargé par ce nouvel EntityManager, $clinicVet/$refugeVet par l'ancien EntityManager —
        // deux instances PHP différentes pour la même clinique en base, donc pas comparables via ===
        $clinicIds = array_map(fn($c) => $c->getId(), $pendingClient->getClinics()->toArray());  // ← préparation
        self::assertContains($clinicVet->getClinic()->getId(), $clinicIds);                     // ← comparaison 1
        self::assertNotContains($refugeVet->getClinic()->getId(), $clinicIds);                 // ← comparaison 2
    }
}
