<?php

namespace App\Form;

use App\Entity\Owner;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OwnerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom')
            ->add('prenom')
            ->add('adresse')
            ->add('telephone')
            ->add('email', EmailType::class, [
                'required' => false,
            ])
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'Aucun utilisateur lié',
            ])
            ->add('createdBy', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'Créateur (optionnel)',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Owner::class,
        ]);
    }
}