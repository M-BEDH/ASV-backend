<?php

namespace App\Tests\Controller;

use App\Entity\MedicalConsultation;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MedicalConsultationControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private EntityRepository $medicalConsultationRepository;
    private string $path = '/medical/consultation/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->medicalConsultationRepository = $this->manager->getRepository(MedicalConsultation::class);

        foreach ($this->medicalConsultationRepository->findAll() as $object) {
            $this->manager->remove($object);
        }

        $this->manager->flush();
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('MedicalConsultation index');
    }

    public function testNew(): void
    {
        $this->client->request('GET', sprintf('%snew', $this->path));
        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'medical_consultation[dateConsultation]' => '2026-03-05T10:00',
            'medical_consultation[motif]' => 'Annual check',
            'medical_consultation[compteRendu]' => 'No issues',
        ]);

        self::assertResponseRedirects('/medical/consultation');
        self::assertSame(1, $this->medicalConsultationRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = (new MedicalConsultation())
            ->setDateConsultation(new \DateTime('2026-03-05 10:00:00'))
            ->setMotif('Follow-up consultation');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('MedicalConsultation');
    }

    public function testEdit(): void
    {
        $fixture = (new MedicalConsultation())
            ->setDateConsultation(new \DateTime('2026-03-05 10:00:00'))
            ->setMotif('Initial reason');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'medical_consultation[dateConsultation]' => '2026-03-06T11:30',
            'medical_consultation[motif]' => 'Updated reason',
        ]);

        self::assertResponseRedirects('/medical/consultation');
        self::assertSame('Updated reason', $this->medicalConsultationRepository->find($fixture->getId())?->getMotif());
    }

    public function testRemove(): void
    {
        $fixture = (new MedicalConsultation())
            ->setDateConsultation(new \DateTime('2026-03-05 10:00:00'))
            ->setMotif('To remove');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/medical/consultation');
        self::assertSame(0, $this->medicalConsultationRepository->count([]));
    }
}