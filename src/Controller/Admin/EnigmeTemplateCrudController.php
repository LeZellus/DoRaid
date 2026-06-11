<?php

namespace App\Controller\Admin;

use App\Entity\EnigmeTemplate;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class EnigmeTemplateCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return EnigmeTemplate::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Énigme (template)')
            ->setEntityLabelInPlural('Énigmes des templates')
            ->setDefaultSort(['raidTemplate' => 'ASC', 'orderNumber' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('raidTemplate', 'Type de raid'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('raidTemplate', 'Type de raid');
        yield IntegerField::new('orderNumber', 'Ordre');
        yield TextField::new('title', 'Titre');
    }
}
