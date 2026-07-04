<?php

namespace App\Controller\Admin;

use App\Entity\Feedback;
use App\Entity\FeedbackStatus;
use App\Entity\FeedbackType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

class FeedbackCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Feedback::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Feedback')
            ->setEntityLabelInPlural('Feedbacks')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(30)
            ->showEntityActionsInlined()
            ->setSearchFields(['title', 'description']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('type')->setChoices([
                'Bug'        => FeedbackType::Bug->value,
                'Suggestion' => FeedbackType::Suggestion->value,
            ]))
            ->add(ChoiceFilter::new('status')->setChoices([
                'Ouvert'   => FeedbackStatus::Open->value,
                'En cours' => FeedbackStatus::InProgress->value,
                'Résolu'   => FeedbackStatus::Done->value,
                'Rejeté'   => FeedbackStatus::Rejected->value,
            ]));
    }

    public function configureFields(string $pageName): iterable
    {
        // Les valeurs sont les cas d'enum eux-mêmes (pas ->value) : Symfony a besoin de
        // retrouver l'instance FeedbackType/FeedbackStatus dans la liste au moment de
        // réhydrater le formulaire, sinon il réinjecte la string soumise telle quelle
        // dans setType()/setStatus() qui attendent l'enum.
        $typeChoices   = [
            '🐛 Bug'        => FeedbackType::Bug,
            '💡 Suggestion' => FeedbackType::Suggestion,
        ];
        $statusChoices = [
            'Ouvert'   => FeedbackStatus::Open,
            'En cours' => FeedbackStatus::InProgress,
            'Résolu'   => FeedbackStatus::Done,
            'Rejeté'   => FeedbackStatus::Rejected,
        ];

        // Convertit chaque cas d'enum en string pour l'attribut HTML value="...".
        // Reste polymorphe (mixed) car Symfony peut aussi l'appeler avec une valeur
        // déjà scalaire selon le contexte.
        $enumChoiceValue = fn (mixed $value): ?string => $value instanceof \BackedEnum ? $value->value : $value;

        yield IdField::new('id', 'ID')->onlyOnIndex();
        yield ChoiceField::new('type', 'Type')
            ->setChoices($typeChoices)
            ->setFormTypeOption('choice_value', $enumChoiceValue)
            ->renderAsBadges([
                FeedbackType::Bug->value        => 'danger',
                FeedbackType::Suggestion->value => 'primary',
            ]);
        yield ChoiceField::new('status', 'Statut')
            ->setChoices($statusChoices)
            ->setFormTypeOption('choice_value', $enumChoiceValue)
            ->renderAsBadges([
                FeedbackStatus::Open->value       => 'warning',
                FeedbackStatus::InProgress->value => 'primary',
                FeedbackStatus::Done->value       => 'success',
                FeedbackStatus::Rejected->value   => 'danger',
            ]);
        yield TextField::new('title', 'Titre');
        yield TextareaField::new('description', 'Description')->hideOnIndex();
        yield TextField::new('page', 'Page')->hideOnIndex();
        yield AssociationField::new('user', 'Utilisateur')->hideOnForm();
        yield DateTimeField::new('createdAt', 'Soumis le')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->hideOnForm();
    }
}
