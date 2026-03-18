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




/*   ---
  Lignes 28-31 — assertMatchesRegularExpression :
  ▎ On vérifie que l'ID respecte exactement le format UUID v4. La regex peut faire peur mais c'est simple à lire par morceaux :

  /^                        début de la chaîne
    [0-9a-f]{8}             8 caractères hex        ex: a3f2c1d4
    -                       tiret
    [0-9a-f]{4}             4 caractères hex        ex: b2c1
    -
    4[0-9a-f]{3}            commence par 4 (version 4 de l'UUID)   ex: 4f3a
    -
    [89ab][0-9a-f]{3}       commence par 8, 9, a ou b (variante)   ex: a3f2
    -
    [0-9a-f]{12}            12 caractères hex       ex: d4e5f6a7b8c9
  $/                        fin de la chaîne

  Un UUID v4 ressemble à ça : a3f2c1d4-b2c1-4f3a-a3f2-d4e5f6a7b8c9

  ---
  Pourquoi c'est important pour le jury ?

  Tu ne vérifies pas juste que l'ID existe — tu vérifies que c'est un vrai UUID v4 bien formé. Ça prouve que tu comprends le format de tes données, pas juste que "ça marche".*/




        // Format UUID v4 : xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx (36 caractères)
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
