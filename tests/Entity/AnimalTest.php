<?php

namespace App\Tests\Entity;

use App\Entity\Animal;
use PHPUnit\Framework\TestCase;

class AnimalTest extends TestCase
{
    public function testNomEtEspece(): void
    {
        $animal = new Animal();
        $animal->setNom('Rex');
        $animal->setEspece('Chien');

        self::assertSame('Rex', $animal->getNom());
        self::assertSame('Chien', $animal->getEspece());
    }

    public function testIdEstUuidAutoGenere(): void
    {
        $animal = new Animal();

        // L'UUID est généré dans le constructeur, il ne doit pas être null
        self::assertNotNull($animal->getId());

        // Format UUID v4 : xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
        //   [0-9a-f]{8}-[0-9a-f]{4}-        8 puis 4 caractères hex
        //   4[0-9a-f]{3}-                   commence par 4 (version 4)
        //   [89ab][0-9a-f]{3}-              commence par 8, 9, a ou b (variante)
        //   [0-9a-f]{12}                    12 caractères hex
        // Exemple : a3f2c1d4-b2c1-4f3a-a3f2-d4e5f6a7b8c9
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $animal->getId()
        );
    }

    public function testDeuxAnimauxOntDesIdsDistincts(): void
    {
        $animal1 = new Animal();
        $animal2 = new Animal();

        self::assertNotSame($animal1->getId(), $animal2->getId());
    }

    public function testCreatedAtEstDefiniALaCreation(): void
    {
        $animal = new Animal();

        self::assertNotNull($animal->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $animal->getCreatedAt());
    }

    public function testValeursOptionnellesNullesParDefaut(): void
    {
        $animal = new Animal();

        self::assertNull($animal->getNom());
        self::assertNull($animal->getEspece());
        self::assertNull($animal->getRace());
        self::assertNull($animal->getDateNaissance());
        self::assertNull($animal->getRemarques());
        self::assertNull($animal->getProprietaire());
    }
}
