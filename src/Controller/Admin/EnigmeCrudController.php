<?php

namespace App\Controller\Admin;

use App\Entity\Enigme;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;

class EnigmeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Enigme::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Énigme')
            ->setEntityLabelInPlural('Énigmes')
            ->setDefaultSort(['raid' => 'ASC', 'orderNumber' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('raid', 'Raid'))
            ->add(BooleanFilter::new('resolved', 'Résolue'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('raid', 'Raid')->setDisabled();
        yield IntegerField::new('orderNumber', 'Ordre');
        yield TextField::new('title', 'Titre');
        yield BooleanField::new('resolved', 'Résolue');
    }
}
