<?php

namespace App\Tests\Controller;

final class MedicalConsultationControllerTest extends ApiTestCase
{
    public function testList(): void
    {
        $vet = $this->createVet();
        $token = $this->getToken($vet->getEmail());

        $data = $this->request('GET', '/api/consultations', [], $token);

        self::assertResponseStatusCodeSame(200);
        self::assertIsArray($data);
    }

    public function testCreate(): void
    {
        $vet = $this->createVet();
        $token = $this->getToken($vet->getEmail());

        $animal = $this->request('POST', '/api/animals', ['nom' => 'Rex', 'espece' => 'Chien'], $token);

        $data = $this->request('POST', '/api/consultations', [
            'animalId'         => $animal['id'],
            'dateConsultation' => '2025-03-16 10:00',
            'motif'            => 'Vaccin',
        ], $token);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('Vaccin', $data['motif']);
    }

    public function testCreateMissingFields(): void
    {
        $vet = $this->createVet();
        $token = $this->getToken($vet->getEmail());

        $this->request('POST', '/api/consultations', ['motif' => 'Vaccin'], $token);

        self::assertResponseStatusCodeSame(400);
    }

    public function testCreateAsClientForbidden(): void
    {
        $client = $this->createUserClient();
        $token = $this->getToken($client->getEmail());

        $this->request('POST', '/api/consultations', [
            'animalId'         => 'some-id',
            'dateConsultation' => '2025-03-16 10:00',
            'motif'            => 'Vaccin',
        ], $token);

        self::assertResponseStatusCodeSame(403);
    }

    public function testUpdate(): void
    {
        $vet = $this->createVet();
        $token = $this->getToken($vet->getEmail());

        $animal = $this->request('POST', '/api/animals', ['nom' => 'Rex', 'espece' => 'Chien'], $token);
        $created = $this->request('POST', '/api/consultations', [
            'animalId'         => $animal['id'],
            'dateConsultation' => '2025-03-16 10:00',
            'motif'            => 'Vaccin',
        ], $token);

        $updated = $this->request('PUT', '/api/consultations/' . $created['id'], ['motif' => 'Urgence'], $token);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('Urgence', $updated['motif']);
    }

    public function testDelete(): void
    {
        $vet = $this->createVet();
        $token = $this->getToken($vet->getEmail());

        $animal = $this->request('POST', '/api/animals', ['nom' => 'Rex', 'espece' => 'Chien'], $token);
        $created = $this->request('POST', '/api/consultations', [
            'animalId'         => $animal['id'],
            'dateConsultation' => '2025-03-16 10:00',
            'motif'            => 'Vaccin',
        ], $token);

        $this->request('DELETE', '/api/consultations/' . $created['id'], [], $token);

        self::assertResponseStatusCodeSame(204);
    }

    public function testDeleteNotFound(): void
    {
        $vet = $this->createVet();
        $token = $this->getToken($vet->getEmail());

        $this->request('DELETE', '/api/consultations/inexistant-id', [], $token);

        self::assertResponseStatusCodeSame(404);
    }
}
