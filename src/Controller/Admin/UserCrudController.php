<?php

namespace App\Controller\Admin;

use App\Constant\RoleConstants;
use App\Entity\Clinic;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Responsable')
            ->setEntityLabelInPlural('Utilisateurs')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPageTitle(Crud::PAGE_NEW, 'Ajouter un responsable / directeur');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::EDIT);
    }

    public function persistEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        /** @var User $entityInstance */

        // Crée la clinique depuis les champs transitoires et lie le responsable
        $clinicName = $entityInstance->getNewClinicName();
        if ($clinicName) {
            $clinic = new Clinic();
            $clinic->setName($clinicName);
            $clinic->setType($entityInstance->getNewClinicType() ?? 'clinique');
            $entityManager->persist($clinic);
            $entityInstance->setClinic($clinic);
        }

        $entityInstance->setRole(RoleConstants::RESPONSABLE);
        $entityInstance->setIsVet($entityInstance->isVet());
        $entityInstance->setPassword(null);

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnIndex()->hideOnForm();
        yield TextField::new('name', 'Nom');
        yield TextField::new('email', 'Email');
        yield ChoiceField::new('role', 'Rôle')
            ->setChoices([
                'Vétérinaire'  => 'veterinaire',
                'Responsable'  => 'responsable',
                'Assistant'    => 'assistant',
                'Bénévole'     => 'benevole',
                'Client'       => 'client',
                'Super Admin'  => 'super_admin',
            ])
            ->renderAsBadges([
                'responsable' => 'warning',
                'super_admin' => 'danger',
                'veterinaire' => 'success',
                'assistant'   => 'success',
                'benevole'    => 'secondary',
                'client'      => 'primary',
            ])
            ->hideWhenCreating();

        yield BooleanField::new('isVet', 'Vétérinaire ?')
            ->renderAsSwitch(false)
            ->hideOnForm();

        // Champs de création de la clinique — uniquement sur le formulaire NEW
        if ($pageName === Crud::PAGE_NEW) {
            yield TextField::new('newClinicName', 'Nom de l\'établissement');
            yield ChoiceField::new('newClinicType', 'Type d\'établissement')
                ->setChoices([
                    'Clinique'    => 'clinique',
                    'Refuge'      => 'refuge',
                    'Association' => 'association',
                ]);
            yield BooleanField::new('isVet', 'Le responsable est vétérinaire ?')
                ->renderAsSwitch(false)
                ->setHelp('Si "Non", ce responsable ne sera pas proposé dans la liste des vétérinaires.');
        }

        yield DateTimeField::new('createdAt', 'Créé le')
            ->hideOnForm()
            ->setFormat('dd/MM/yyyy HH:mm');
    }
}
