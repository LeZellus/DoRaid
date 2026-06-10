<?php

namespace App\Form;

use App\Entity\Guild;
use App\Entity\Server;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class GuildType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la guilde',
                'constraints' => [
                    new NotBlank(message: 'Entrez un nom.'),
                    new Length(min: 2, max: 100),
                ],
                'attr' => ['placeholder' => 'Ex : Les Gardiens'],
            ])
            ->add('server', EntityType::class, [
                'label' => 'Serveur',
                'class' => Server::class,
                'choice_label' => 'name',
                'placeholder' => '— Choisir un serveur —',
                'constraints' => [new NotBlank(message: 'Choisissez un serveur.')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Guild::class]);
    }
}
