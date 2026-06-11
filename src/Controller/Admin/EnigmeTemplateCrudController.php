<?php

namespace App\Controller\Admin;

use App\Entity\EnigmeTemplate;
use Doctrine\ORM\EntityManagerInterface;
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

    public function updateEntity(EntityManagerInterface $em, object $entityInstance): void
    {
        parent::updateEntity($em, $entityInstance);

        $em->getConnection()->executeStatement(
            'UPDATE enigme e
             JOIN raid r ON e.raid_id = r.id
             JOIN enigme_template et ON et.id = :templateId
             SET e.title = :title,
                 e.source_template_id = :templateId
             WHERE et.raid_template_id = r.raid_template_id
               AND e.order_number = et.order_number',
            [
                'title'      => $entityInstance->getTitle(),
                'templateId' => $entityInstance->getId(),
            ]
        );
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
