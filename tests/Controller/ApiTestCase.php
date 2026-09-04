<?php

namespace App\Tests\Controller;

use App\Entity\Animal;
use App\Entity\Clinic;
use App\Entity\MedicalConsultation;
use App\Entity\Owner;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase; // permet de simuler des requêtes HTTP sans vrai serveur
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class ApiTestCase extends WebTestCase // base des tests avec ce qui est commun à tous (DRY)
{
    protected KernelBrowser $client; 
    // $client → simule un navigateur HTTP pour envoyer de vraies requetes vers les routes symfo pdt les tests
    protected EntityManagerInterface $em;
    // $em → Entity Manager de Doctrine, pour manipuler la base directement (persist, flush, remove) sans passer par l'API

    protected function setUp(): void
    {
        self::ensureKernelShutdown(); // Assure que le kernel est redémarré pour chaque test pour éviter les effets de bord (error token test MedicalConsultationControllerTest)
        $this->client = static::createClient();
        $manager = static::getContainer()->get('doctrine')->getManager();
        assert($manager instanceof EntityManagerInterface);
        $this->em = $manager;
        $this->cleanDatabase();
    }

    // Vide la base dans le bon ordre (respect des clés étrangères)
    private function cleanDatabase(): void
    {
        foreach ($this->em->getRepository(MedicalConsultation::class)->findAll() as $obj) {
            $this->em->remove($obj);
        }
        foreach ($this->em->getRepository(Animal::class)->findAll() as $obj) {
            $this->em->remove($obj);
        }
        foreach ($this->em->getRepository(Owner::class)->findAll() as $obj) {
            $this->em->remove($obj);
        }
        foreach ($this->em->getRepository(User::class)->findAll() as $obj) {
            $this->em->remove($obj);
        }
        foreach ($this->em->getRepository(Clinic::class)->findAll() as $obj) {
            $this->em->remove($obj);
        }
        $this->em->flush();
    }

    // Crée un vétérinaire avec sa clinique (type clinique, refuge ou association)
    protected function createVet(string $email = 'vet@test.com', string $clinicType = 'clinique', string $password = 'password'): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $clinic = new Clinic();
        $clinic->setName("{$clinicType} Test");
        $clinic->setType($clinicType);
        $this->em->persist($clinic);

        $user = new User();
        $user->setEmail($email);
        $user->setName("Dr Test{$clinicType}");
        $user->setRole('veterinaire');
        $user->setIsVet(true);
        $user->setPassword($hasher->hashPassword($user, $password));
        $user->setClinic($clinic);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    // Crée un vétérinaire dans une clinique déjà existante (utile pour tester deux rôles d'une même clinique)
    protected function createVetInClinic(Clinic $clinic, string $email = 'vet2@test.com', string $password = 'password'): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setName('Dr Test Same Clinic');
        $user->setRole('veterinaire');
        $user->setIsVet(true);
        $user->setPassword($hasher->hashPassword($user, $password));
        $user->setClinic($clinic);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    // Crée un pré-compte (password null) avec une clinique, prêt à être activé via /register
    protected function createPendingUser(string $email = 'pending@test.com', string $role = 'veterinaire'): User
    {
        $clinic = new Clinic();
        $clinic->setName('Clinique Pending');
        $this->em->persist($clinic);

        $user = new User();
        $user->setEmail($email);
        $user->setName('Pending User');
        $user->setRole($role);
        $user->setPassword(null);
        $user->setClinic($clinic);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    // Crée un bénévole rattaché à une clinique du type donné (clinique, refuge, association)
    protected function createBenevole(string $email = 'benevole@test.com', string $clinicType = 'clinique', string $password = 'password'): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $clinic = new Clinic();
        $clinic->setName('Clinique Benevole Test');
        $clinic->setType($clinicType);
        $this->em->persist($clinic);

        $user = new User();
        $user->setEmail($email);
        $user->setName('Benevole Test');
        $user->setRole('benevole');
        $user->setPassword($hasher->hashPassword($user, $password));
        $user->setClinic($clinic);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    // Crée un assistant rattaché à une clinique du type donné (clinique, refuge, association)
    protected function createAssistant(string $email = 'assistant@test.com', string $clinicType = 'clinique', string $password = 'password'): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $clinic = new Clinic();
        $clinic->setName('Clinique Assistant Test');
        $clinic->setType($clinicType);
        $this->em->persist($clinic);

        $user = new User();
        $user->setEmail($email);
        $user->setName('Assistant Test');
        $user->setRole('assistant');
        $user->setPassword($hasher->hashPassword($user, $password));
        $user->setClinic($clinic);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    // Crée un utilisateur client (pas de clinique)
    protected function createUserClient(string $email = 'client@test.com', string $password = 'password'): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setName('Client Test');
        $user->setRole('client');
        $user->setPassword($hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    // Login et retourne le token JWT
    protected function getToken(string $email, string $password = 'password'): string
    {
        $this->client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => $password])
        );

        $data = json_decode($this->client->getResponse()->getContent(), true);
        return $data['token'];
    }

    // Envoie une requête JSON et retourne le tableau de réponse
    protected function request(string $method, string $url, array $data = [], string $token = ''): array
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];
        if ($token) {
            $headers['HTTP_AUTHORIZATION'] = "Bearer {$token}";
        }

        $this->client->request(
            $method,
            $url,
            [],
            [],
            $headers,
            $data ? json_encode($data) : null
        );

        $content = $this->client->getResponse()->getContent();
        return $content ? json_decode($content, true) ?? [] : [];
    }
}
