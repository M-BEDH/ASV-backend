<?php

namespace App\Controller\Admin;

use App\Entity\Clinic;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ClinicCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Clinic::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::NEW);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Clinique')
            ->setEntityLabelInPlural('Cliniques')
            ->setDefaultSort(['name' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'Nom');
        yield ChoiceField::new('type', 'Type')->setChoices([
            'Clinique'    => 'clinique',
            'Refuge'      => 'refuge',
            'Association' => 'association',
        ]);
        yield DateTimeField::new('createdAt', 'Créé le')
            ->hideOnForm()
            ->setFormat('dd/MM/yyyy');
    }
}
