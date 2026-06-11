<?php

namespace App\Controller\Admin;

use App\Entity\RaidTemplate;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;

class RaidTemplateCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return RaidTemplate::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Type de raid')
            ->setEntityLabelInPlural('Types de raid')
            ->setDefaultSort(['name' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nom');
        yield IntegerField::new('minParticipants', 'Min. joueurs');
        yield IntegerField::new('maxParticipants', 'Max. joueurs');
        yield ImageField::new('imagePath', 'Image')
            ->setUploadDir('public/uploads/raid-templates')
            ->setBasePath('uploads/raid-templates')
            ->setRequired(false);
    }
}
