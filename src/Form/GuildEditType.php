<?php

namespace App\Form;

use App\Entity\Guild;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Url;

class GuildEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la guilde',
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'constraints' => [
                    new Length(max: 5000, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.'),
                ],
                'attr'     => ['rows' => 5],
            ])
            ->add('imageFile', FileType::class, [
                'label'       => 'Image de la guilde',
                'mapped'      => false,
                'required'    => false,
                'constraints' => [
                    new File([
                        'maxSize'   => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                        'mimeTypesMessage' => 'Format accepté : JPG, PNG, WebP, GIF.',
                    ]),
                ],
            ])
            ->add('discordWebhookUrl', UrlType::class, [
                'label'       => 'Webhook Discord (optionnel)',
                'required'    => false,
                'attr'        => ['placeholder' => 'https://discord.com/api/webhooks/...'],
                'help'        => 'Collez ici l\'URL de votre webhook Discord pour recevoir les notifications de raid.',
                'constraints' => [
                    new Url(message: 'L\'URL du webhook Discord n\'est pas valide.', requireTld: true),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Guild::class]);
    }
}
