<?php

namespace App\DataFixtures;

use App\Constant\RoleConstants;
use App\Entity\Animal;
use App\Entity\Clinic;
use App\Entity\MedicalConsultation;
use App\Entity\Owner;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

// Jeu de données de démonstration pour l'environnement de développement.
// Mot de passe commun à tous les comptes ci-dessous : Test1234!
class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $hasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $password = 'Test1234!';

        //  Cliniques 
        $clinique = new Clinic();
        $clinique->setName('Clinique du Parc');
        $clinique->setType('clinique');
        $manager->persist($clinique);

        $refuge = new Clinic();
        $refuge->setName('Refuge de la Vallée');
        $refuge->setType('refuge');
        $manager->persist($refuge);

        //  Staff -- Clinique du Parc 
        $responsableClinique = new User();
        $responsableClinique->setEmail('responsable@clinique.test');
        $responsableClinique->setName('Claire Directrice');
        $responsableClinique->setRole(RoleConstants::RESPONSABLE);
        $responsableClinique->setPassword($this->hasher->hashPassword($responsableClinique, $password));
        $responsableClinique->setClinic($clinique);
        $manager->persist($responsableClinique);

        $veto = new User();
        $veto->setEmail('veto@clinique.test');
        $veto->setName('Dr Martin Véto');
        $veto->setRole(RoleConstants::VETERINAIRE);
        $veto->setIsVet(true);
        $veto->setPassword($this->hasher->hashPassword($veto, $password));
        $veto->setClinic($clinique);
        $manager->persist($veto);

        $assistant = new User();
        $assistant->setEmail('assistant@clinique.test');
        $assistant->setName('Sophie Assistante');
        $assistant->setRole(RoleConstants::ASSISTANT);
        $assistant->setPassword($this->hasher->hashPassword($assistant, $password));
        $assistant->setClinic($clinique);
        $manager->persist($assistant);

        //  Staff -- Refuge de la Vallée 
        $responsableRefuge = new User();
        $responsableRefuge->setEmail('responsable@refuge.test');
        $responsableRefuge->setName('Marc Directeur');
        $responsableRefuge->setRole(RoleConstants::RESPONSABLE);
        $responsableRefuge->setPassword($this->hasher->hashPassword($responsableRefuge, $password));
        $responsableRefuge->setClinic($refuge);
        $manager->persist($responsableRefuge);

        $benevole = new User();
        $benevole->setEmail('benevole@refuge.test');
        $benevole->setName('Julie Bénévole');
        $benevole->setRole(RoleConstants::BENEVOLE);
        $benevole->setPassword($this->hasher->hashPassword($benevole, $password));
        $benevole->setClinic($refuge);
        $manager->persist($benevole);

        //  Client + Owner 
        // Owner rattaché uniquement à une vraie clinique (jamais à un refuge)
        $clientUser = new User();
        $clientUser->setEmail('client@test.fr');
        $clientUser->setName('Jean Dupont');
        $clientUser->setRole(RoleConstants::CLIENT);
        $clientUser->setPassword($this->hasher->hashPassword($clientUser, $password));
        $clientUser->addClinic($clinique);
        $manager->persist($clientUser);

        $owner = new Owner();
        $owner->setNom('Dupont');
        $owner->setPrenom('Jean');
        $owner->setEmail('client@test.fr');
        $owner->setTelephone('0612345678');
        $owner->setAdresse('12 rue des Lilas, 75000 Paris');
        $owner->setCreatedBy($responsableClinique);
        $owner->addClinic($clinique);
        $owner->setUser($clientUser);
        $manager->persist($owner);

        // Animaux 
        $rex = new Animal();
        $rex->setNom('Rex');
        $rex->setEspece('Chien');
        $rex->setRace('Labrador');
        $rex->setDateNaissance(new \DateTime('-3 years'));
        $rex->setClinic($clinique);
        $rex->setProprietaire($owner);
        $rex->setCreatedBy($veto);
        $manager->persist($rex);

        // Animal du refuge, sans propriétaire : état normal en attente d'adoption
        $miaou = new Animal();
        $miaou->setNom('Miaou');
        $miaou->setEspece('Chat');
        $miaou->setDateNaissance(new \DateTime('-1 year'));
        $miaou->setRemarques('Recueilli errant, en attente d\'adoption.');
        $miaou->setClinic($refuge);
        $miaou->setCreatedBy($benevole);
        $manager->persist($miaou);

        // Consultation médicale 
        $consultation = new MedicalConsultation();
        $consultation->setAnimal($rex);
        $consultation->setMotif('Vaccination annuelle');
        $consultation->setDateConsultation(new \DateTime('-1 month'));
        $consultation->setCompteRendu('Animal en bonne santé générale. Poids stable.');
        $consultation->setTraitements('Rappel vaccin CHPPi + Rage.');
        $consultation->setClinic($clinique);
        $consultation->setVeterinaire($veto);
        $manager->persist($consultation);

        // Super administrateur 
        // CreateSuperAdminCommand refuse de s'exécuter si un super_admin existe déjà ;
        // les fixtures purgeant la base, on en recrée un pour garder /admin accessible.
        $superAdmin = new User();
        $superAdmin->setEmail('super-admin@asv.test');
        $superAdmin->setName('Super Admin');
        $superAdmin->setRole(RoleConstants::SUPER_ADMIN);
        $superAdmin->setPassword($this->hasher->hashPassword($superAdmin, $password));
        $manager->persist($superAdmin);

        $manager->flush();
    }
}
