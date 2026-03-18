<?php

namespace App\Tests\Controller;

final class UserControllerTest extends ApiTestCase
{
    public function testRegister(): void
    {
        $this->request('POST', '/api/auth/register', [
            'email'    => 'new@test.com',
            'password' => 'password',
            'name'     => 'Nouveau',
            'role'     => 'veterinaire',
        ]);

        self::assertResponseStatusCodeSame(201);
    }

    public function testRegisterMissingFields(): void
    {
        $this->request('POST', '/api/auth/register', ['email' => 'test@test.com']);

        self::assertResponseStatusCodeSame(400);
    }

    public function testRegisterDuplicateEmail(): void
    {
        $this->createVet('dup@test.com');

        $this->request('POST', '/api/auth/register', [
            'email'    => 'dup@test.com',
            'password' => 'password',
            'name'     => 'Doublon',
            'role'     => 'veterinaire',
        ]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testLogin(): void
    {
        $this->createVet('login@test.com');

        $data = $this->request('POST', '/api/auth/login', [
            'email'    => 'login@test.com',
            'password' => 'password',
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertArrayHasKey('token', $data);
    }

    public function testLoginInvalidCredentials(): void
    {
        $this->createVet('vet@test.com');

        $this->request('POST', '/api/auth/login', [
            'email'    => 'vet@test.com',
            'password' => 'mauvais_mot_de_passe',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testMe(): void
    {
        $this->createVet('me@test.com');
        $token = $this->getToken('me@test.com');

        $data = $this->request('GET', '/api/auth/me', [], $token);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('me@test.com', $data['email']);
    }
}
