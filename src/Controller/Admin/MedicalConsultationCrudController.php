<?php

namespace App\Controller\Admin;

use App\Entity\MedicalConsultation;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

class MedicalConsultationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return MedicalConsultation::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Consultation')
            ->setEntityLabelInPlural('Consultations')
            ->setDefaultSort(['dateConsultation' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnIndex();
        yield DateTimeField::new('dateConsultation', 'Date')->setFormat('dd/MM/yyyy HH:mm');
        yield AssociationField::new('animal', 'Animal');
        yield AssociationField::new('veterinaire', 'Vétérinaire');
        yield AssociationField::new('clinic', 'Établissement');
        yield TextareaField::new('motif', 'Motif')->hideOnIndex();
        yield TextareaField::new('compteRendu', 'Compte rendu')->hideOnIndex();
        yield DateTimeField::new('createdAt', 'Créé le')
            ->hideOnIndex()
            ->setFormat('dd/MM/yyyy HH:mm');
    }
}
