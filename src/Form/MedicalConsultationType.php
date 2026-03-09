<?php

namespace App\Form;

use App\Entity\Animal;
use App\Entity\MedicalConsultation;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MedicalConsultationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('animal', EntityType::class, [
                'class' => Animal::class,
                'choice_label' => 'nom',
                'required' => false,
                'placeholder' => 'Animal (optionnel)',
            ])
            ->add('dateConsultation', DateTimeType::class, [
                'widget' => 'single_text',
            ])
            ->add('veterinaire', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'Vétérinaire (optionnel)',
            ])
            ->add('motif')
            ->add('compteRendu')
            ->add('traitements');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MedicalConsultation::class,
        ]);
    }
}