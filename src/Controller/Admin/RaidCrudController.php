<?php

namespace App\Controller\Admin;

use App\Entity\Raid;
use App\Entity\RaidStatus;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

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
            ->setPageSize(50)
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('guild', 'Guilde'))
            ->add(EntityFilter::new('raidTemplate', 'Type'))
            ->add(ChoiceFilter::new('status', 'Statut')->setChoices([
                'Ouvert' => RaidStatus::Open->value,
                'Fermé'  => RaidStatus::Closed->value,
            ]));
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('raidTemplate', 'Type');
        yield AssociationField::new('guild', 'Guilde');
        yield AssociationField::new('creator', 'Créateur')->hideOnForm();
        yield ChoiceField::new('status', 'Statut')
            ->setChoices(array_combine(
                array_map(fn(RaidStatus $s) => $s->label(), RaidStatus::cases()),
                RaidStatus::cases()
            ));
        yield DateTimeField::new('scheduledAt', 'Planifié le')->hideOnForm();
        yield DateTimeField::new('createdAt', 'Créé le')->hideOnForm();
    }

    public function deleteEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        foreach ($entityInstance->getParticipants()->toArray() as $participant) {
            $entityManager->remove($participant);
        }
        foreach ($entityInstance->getEnigmes()->toArray() as $enigme) {
            $entityManager->remove($enigme);
        }
        $entityManager->remove($entityInstance);
        $entityManager->flush();
    }
}
