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

    public function updateEntity(EntityManagerInterface $em, mixed $entityInstance): void
    {
        parent::updateEntity($em, $entityInstance);

        // Match enigmes linked explicitly (raids created after sourceTemplate FK was added)
        // OR by raidTemplate+orderNumber for older raids without the FK
        $enigmes = $em->createQuery(
            'SELECT e FROM App\Entity\Enigme e
             JOIN e.raid r
             WHERE e.sourceTemplate = :template
                OR (e.sourceTemplate IS NULL
                    AND e.orderNumber = :order
                    AND r.raidTemplate = :raidTemplate)'
        )->setParameters([
            'template'    => $entityInstance,
            'order'       => $entityInstance->getOrderNumber(),
            'raidTemplate' => $entityInstance->getRaidTemplate(),
        ])->getResult();

        foreach ($enigmes as $enigme) {
            $enigme->setTitle($entityInstance->getTitle());
        }

        $em->flush();
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
