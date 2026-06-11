<?php

namespace App\Controller\Admin;

use App\Entity\Raid;
use App\Entity\RaidStatus;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

class RaidCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Raid::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Raid')
            ->setEntityLabelInPlural('Raids')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->showEntityActionsInlined();
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
            ->add(EntityFilter::new('guild', 'Guilde'))
            ->add(EntityFilter::new('raidTemplate', 'Type'))
            ->add(ChoiceFilter::new('status', 'Statut')->setChoices([
                'Ouvert'  => RaidStatus::Open->value,
                'Fermé'   => RaidStatus::Closed->value,
            ]));
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('raidTemplate', 'Type')->setDisabled();
        yield AssociationField::new('guild', 'Guilde')->setDisabled();
        yield ChoiceField::new('status', 'Statut')
            ->setChoices(array_combine(
                array_map(fn(RaidStatus $s) => $s->label(), RaidStatus::cases()),
                RaidStatus::cases()
            ));
        yield DateTimeField::new('scheduledAt', 'Planifié le')->setRequired(false);
        yield TextField::new('description', 'Description')->hideOnIndex()->setRequired(false);
        yield DateTimeField::new('createdAt', 'Créé le')->hideOnForm();
    }
}
