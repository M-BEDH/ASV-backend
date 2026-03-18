<?php

namespace App\Tests\Controller;

final class AnimalControllerTest extends ApiTestCase
{
    public function testList(): void
    {
        $vet = $this->createVet();
        $token = $this->getToken($vet->getEmail());

        $data = $this->request('GET', '/api/animals', [], $token);

        self::assertResponseStatusCodeSame(200);
        self::assertIsArray($data);
    }

    public function testCreate(): void
    {
        $vet = $this->createVet();
        $token = $this->getToken($vet->getEmail());

        $data = $this->request('POST', '/api/animals', [
            'nom'    => 'Rex',
            'espece' => 'Chien',
        ], $token);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('Rex', $data['nom']);
        self::assertSame('Chien', $data['espece']);
    }

    public function testCreateMissingFields(): void
    {
        $vet = $this->createVet();
        $token = $this->getToken($vet->getEmail());

        $this->request('POST', '/api/animals', ['nom' => 'Rex'], $token);

        self::assertResponseStatusCodeSame(400);
    }

    public function testCreateAsClientForbidden(): void
    {
        $client = $this->createUserClient();
        $token = $this->getToken($client->getEmail());

        $this->request('POST', '/api/animals', ['nom' => 'Rex', 'espece' => 'Chien'], $token);

        self::assertResponseStatusCodeSame(403);
    }

    public function testUpdate(): void
    {
        $vet = $this->createVet();
        $token = $this->getToken($vet->getEmail());

        $created = $this->request('POST', '/api/animals', ['nom' => 'Rex', 'espece' => 'Chien'], $token);
        $updated = $this->request('PUT', '/api/animals/' . $created['id'], ['nom' => 'Max'], $token);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('Max', $updated['nom']);
    }

    public function testDelete(): void
    {
        $vet = $this->createVet();
        $token = $this->getToken($vet->getEmail());

        $created = $this->request('POST', '/api/animals', ['nom' => 'Rex', 'espece' => 'Chien'], $token);
        $this->request('DELETE', '/api/animals/' . $created['id'], [], $token);

        self::assertResponseStatusCodeSame(204);
    }

    public function testDeleteNotFound(): void
    {
        $vet = $this->createVet();
        $token = $this->getToken($vet->getEmail());

        $this->request('DELETE', '/api/animals/inexistant-id', [], $token);

        self::assertResponseStatusCodeSame(404);
    }
}
