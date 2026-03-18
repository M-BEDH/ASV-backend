<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class UserTest extends TestCase
{
    public function testEmailEtNom(): void
    {
        $user = new User();
        $user->setEmail('vet@test.com');
        $user->setName('Dr Dupont');

        self::assertSame('vet@test.com', $user->getEmail());
        self::assertSame('Dr Dupont', $user->getName());
    }

    public function testIdEstUuidAutoGenere(): void
    {
        $user = new User();

        self::assertNotNull($user->getId());

        // Format UUID v4 : 36 caractères avec tirets
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $user->getId()
        );
    }

    public function testDeuxUtilisateursOntDesIdsDistincts(): void
    {
        $user1 = new User();
        $user2 = new User();

        self::assertNotSame($user1->getId(), $user2->getId());
    }

    public function testRoleVeterinaire(): void
    {
        $user = new User();
        $user->setRole('veterinaire');

        self::assertSame('veterinaire', $user->getRole());
        // getRoles() doit retourner ROLE_VETERINAIRE 
        self::assertContains('ROLE_VETERINAIRE', $user->getRoles());
    }

    public function testRoleClient(): void
    {
        $user = new User();
        $user->setRole('client');

        self::assertSame('client', $user->getRole());
        self::assertContains('ROLE_CLIENT', $user->getRoles());
    }

    public function testCreatedAtEstDefiniALaCreation(): void
    {
        $user = new User();

        self::assertNotNull($user->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $user->getCreatedAt());
    }

    public function testGetUserIdentifierRetourneLEmail(): void
    {
        $user = new User();
        $user->setEmail('vet@test.com');

        self::assertSame('vet@test.com', $user->getUserIdentifier());
    }

    public function testEmailFormatValide(): void
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $user = new User();

        // Email invalide → doit produire une erreur de validation
        $user->setEmail('pas-un-email');
        $errors = $validator->validateProperty($user, 'email');
        self::assertGreaterThan(0, count($errors));

        // Email valide → aucune erreur
        $user->setEmail('vet@test.com');
        $errors = $validator->validateProperty($user, 'email');
        self::assertCount(0, $errors);
    }
}
