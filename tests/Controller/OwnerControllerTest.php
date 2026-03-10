<?php

namespace App\Tests\Controller;

use App\Entity\Owner;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OwnerControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private EntityRepository $ownerRepository;
    private string $path = '/owner/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->ownerRepository = $this->manager->getRepository(Owner::class);

        foreach ($this->ownerRepository->findAll() as $object) {
            $this->manager->remove($object);
        }

        $this->manager->flush();
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Owner index');
    }

    public function testNew(): void
    {
        $this->client->request('GET', sprintf('%snew', $this->path));
        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'owner[nom]' => 'Durand',
            'owner[prenom]' => 'Alice',
            'owner[email]' => 'alice.durand@example.com',
            'owner[telephone]' => '0601020304',
        ]);

        self::assertResponseRedirects('/owner');
        self::assertSame(1, $this->ownerRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = (new Owner())
            ->setNom('Dupont')
            ->setPrenom('Jean');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Owner');
    }

    public function testEdit(): void
    {
        $fixture = (new Owner())
            ->setNom('Martin')
            ->setPrenom('Paul');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'owner[nom]' => 'MartinMaj',
            'owner[prenom]' => 'PaulMaj',
        ]);

        self::assertResponseRedirects('/owner');
        self::assertSame('MartinMaj', $this->ownerRepository->find($fixture->getId())?->getNom());
    }

    public function testRemove(): void
    {
        $fixture = (new Owner())
            ->setNom('Delete')
            ->setPrenom('Me');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/owner');
        self::assertSame(0, $this->ownerRepository->count([]));
    }
}