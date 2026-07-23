<?php

namespace App\Tests\Controller;

final class OwnerControllerTest extends ApiTestCase
{
    public function testList(): void
    {
        $vet = $this->createVet();
        $token = $this->getToken($vet->getEmail());

        $data = $this->request('GET', '/api/owners', [], $token);

        self::assertResponseStatusCodeSame(200);
    }

    public function testCreate(): void
    {
        $vet = $this->createVet();
        $token = $this->getToken($vet->getEmail());

        $data = $this->request('POST', '/api/owners', [
            'nom'    => 'Dupont',
            'prenom' => 'Jean',
            'email'  => 'dupont@test.com',
        ], $token);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('Dupont', $data['nom']);
        self::assertSame('Jean', $data['prenom']);
    }

    public function testCreateMissingFields(): void
    {
        $vet = $this->createVet();
        $token = $this->getToken($vet->getEmail());

        $this->request('POST', '/api/owners', ['nom' => 'Dupont'], $token);

        self::assertResponseStatusCodeSame(400);
    }

    public function testUpdate(): void
    {
        $vet = $this->createVet();
        $token = $this->getToken($vet->getEmail());

        $created = $this->request('POST', '/api/owners', ['nom' => 'Dupont', 'prenom' => 'Jean', 'email' => 'dupont@test.com'], $token);
        $updated = $this->request('PUT', '/api/owners/' . $created['id'], ['nom' => 'Martin'], $token);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('Martin', $updated['nom']);
    }

    public function testDelete(): void
    {
        $vet = $this->createVet();
        $token = $this->getToken($vet->getEmail());

        $created = $this->request('POST', '/api/owners', ['nom' => 'Dupont', 'prenom' => 'Jean', 'email' => 'dupont@test.com'], $token);
        $this->request('DELETE', '/api/owners/' . $created['id'], [], $token);

        self::assertResponseStatusCodeSame(204);
    }

    public function testDeleteNotFound(): void
    {
        $vet = $this->createVet();
        $token = $this->getToken($vet->getEmail());

        $this->request('DELETE', '/api/owners/inexistant-id', [], $token);

        self::assertResponseStatusCodeSame(404);
    }
}
