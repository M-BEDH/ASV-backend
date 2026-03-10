<?php

namespace App\Tests\Controller;

use App\Entity\Animal;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AnimalControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private EntityRepository $animalRepository;
    private string $path = '/animal/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->animalRepository = $this->manager->getRepository(Animal::class);

        foreach ($this->animalRepository->findAll() as $object) {
            $this->manager->remove($object);
        }

        $this->manager->flush();
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Animal index');

        // Use the $crawler to perform additional assertions e.g.
        // self::assertSame('Some text on the page', $crawler->filter('.p')->first()->text());
    }

    public function testNew(): void
    {
        $this->client->request('GET', sprintf('%snew', $this->path));
        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'animal[nom]' => 'Rex',
            'animal[espece]' => 'Chien',
            'animal[race]' => 'Labrador',
        ]);

        self::assertResponseRedirects('/animal');
        self::assertSame(1, $this->animalRepository->count([]));

        $this->markTestIncomplete('This test was generated');
    }

    public function testShow(): void
    {
        $fixture = (new Animal())
            ->setNom('Rex')
            ->setEspece('Chien')
            ->setRace('Labrador');
        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Animal');

        $this->markTestIncomplete('This test was generated');
    }

    public function testEdit(): void
    {
        $fixture = (new Animal())
            ->setNom('Rex')
            ->setEspece('Chien')
            ->setRace('Labrador');
        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));
        $this->client->submitForm('Update', [
            'animal[nom]' => 'Rex',
            'animal[espece]' => 'Chien',
            'animal[race]' => 'Labrador',
        ]);

        self::assertResponseRedirects('/animal');
        $fixture = $this->animalRepository->findAll();

        $this->markTestIncomplete('This test was generated');
    }

    public function testRemove(): void
    {
        $fixture = (new Animal())
            ->setNom('Rex')
            ->setEspece('Chien')
            ->setRace('Labrador');
        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/animal');
        self::assertSame(0, $this->animalRepository->count([]));

        $this->markTestIncomplete('This test was generated');
    }
}