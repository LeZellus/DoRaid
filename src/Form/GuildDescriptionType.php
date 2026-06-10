<?php

namespace App\Form;

use App\Entity\Guild;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

class GuildDescriptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('description', TextareaType::class, [
            'label'       => false,
            'required'    => false,
            'attr'        => [
                'rows'        => 4,
                'placeholder' => 'Décrivez votre guilde : objectifs, ambiance, prérequis…',
            ],
            'constraints' => [
                new Length(max: 1000, maxMessage: 'La description ne peut pas dépasser 1000 caractères.'),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Guild::class]);
    }
}
