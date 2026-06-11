<?php

namespace App\Form;

use App\Entity\Character;
use App\Entity\GameClass;
use App\Entity\OptimizationLevel;
use App\Entity\Server;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class CharacterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du personnage',
                'constraints' => [
                    new NotBlank(message: 'Entrez un nom.'),
                    new Length(min: 2, max: 50),
                ],
                'attr' => ['placeholder' => 'Ex : Darkpala'],
            ])
            ->add('gameClass', EntityType::class, [
                'label' => 'Classe',
                'class' => GameClass::class,
                'choice_label' => 'name',
                'placeholder' => '— Choisir une classe —',
                'constraints' => [new NotBlank(message: 'Choisissez une classe.')],
            ])
            ->add('server', EntityType::class, [
                'label' => 'Serveur',
                'class' => Server::class,
                'choice_label' => 'name',
                'placeholder' => '— Choisir un serveur —',
                'constraints' => [new NotBlank(message: 'Choisissez un serveur.')],
            ])
            ->add('level', IntegerType::class, [
                'label'    => 'Niveau',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex : 200', 'min' => 1, 'max' => 230],
                'constraints' => [new Range(min: 1, max: 230)],
            ])
            ->add('optimizationLevel', EnumType::class, [
                'label'    => 'Optimisation',
                'class'    => OptimizationLevel::class,
                'required' => false,
                'placeholder' => '— Non renseigné —',
                'choice_label' => fn(OptimizationLevel $o) => $o->label(),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Character::class]);
    }
}
