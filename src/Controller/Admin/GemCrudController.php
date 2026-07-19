<?php

namespace App\Controller\Admin;

use App\Entity\Gem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class GemCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Gem::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Gemme')
            ->setEntityLabelInPlural('Gemmes')
            ->setDefaultSort(['value' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nom');
        yield IntegerField::new('value', 'Valeur (points)');
    }
}
