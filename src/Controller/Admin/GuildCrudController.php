<?php

namespace App\Controller\Admin;

use App\Entity\Guild;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class GuildCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Guild::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Guilde')
            ->setEntityLabelInPlural('Guildes')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nom');
        yield TextField::new('slug', 'Slug')->hideOnIndex();
        yield AssociationField::new('owner', 'Propriétaire');
        yield AssociationField::new('server', 'Serveur');
        yield DateTimeField::new('createdAt', 'Créée le')->hideOnForm();
    }
}
