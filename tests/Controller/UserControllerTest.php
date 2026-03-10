<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class UserControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private EntityRepository $userRepository;
    private string $path = '/user/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->userRepository = $this->manager->getRepository(User::class);

        foreach ($this->userRepository->findAll() as $object) {
            $this->manager->remove($object);
        }

        $this->manager->flush();
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('User index');
    }

    public function testNew(): void
    {
        $this->client->request('GET', sprintf('%snew', $this->path));
        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'user[email]' => 'new.user@example.com',
            'user[name]' => 'New User',
            'user[role]' => 'client',
        ]);

        self::assertResponseRedirects('/user');
        self::assertSame(1, $this->userRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = (new User())
            ->setEmail('show.user@example.com')
            ->setName('Show User')
            ->setRole('assistant');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('User');
    }

    public function testEdit(): void
    {
        $fixture = (new User())
            ->setEmail('edit.user@example.com')
            ->setName('Edit User')
            ->setRole('client');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'user[email]' => 'edited.user@example.com',
            'user[name]' => 'Edited User',
            'user[role]' => 'veterinaire',
        ]);

        self::assertResponseRedirects('/user');
        self::assertSame('edited.user@example.com', $this->userRepository->find($fixture->getId())?->getEmail());
    }

    public function testRemove(): void
    {
        $fixture = (new User())
            ->setEmail('delete.user@example.com')
            ->setName('Delete User')
            ->setRole('assistant');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/user');
        self::assertSame(0, $this->userRepository->count([]));
    }
}