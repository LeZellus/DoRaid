<?php

namespace App\Controller\Admin;

use App\Entity\Mob;
use App\Entity\MobDropRate;
use App\Repository\GemRepository;
use App\Repository\MobDropRateRepository;
use App\Traits\CsrfGuardTrait;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MobCrudController extends AbstractCrudController
{
    use CsrfGuardTrait;

    public static function getEntityFqcn(): string
    {
        return Mob::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Mob')
            ->setEntityLabelInPlural('Mobs')
            ->setDefaultSort(['raidTemplate' => 'ASC', 'name' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('raidTemplate', 'Type de raid'));
    }

    public function configureActions(Actions $actions): Actions
    {
        $editDropRates = Action::new('editDropRates', 'Taux de drop', 'fa fa-percent')
            ->linkToCrudAction('editDropRates');

        return $actions->add(Crud::PAGE_INDEX, $editDropRates);
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('raidTemplate', 'Type de raid');
        yield TextField::new('name', 'Nom');
        yield NumberField::new('expectedPoints', 'Espérance de points')
            ->setNumDecimals(2)
            ->onlyOnIndex()
            ->setSortable(false);
    }

    /**
     * Édition en un seul écran des taux de drop d'un mob (un champ par gemme), plutôt qu'une
     * ligne EasyAdmin séparée par couple mob/gemme (6 mobs × 7 gemmes = 42 lignes plates).
     * #[AdminRoute] est indispensable : sans elle, EasyAdmin ne génère aucune route pour une
     * action personnalisée et linkToCrudAction() retombe silencieusement sur le tableau de bord.
     */
    #[AdminRoute(path: '/{entityId}/taux-de-drop', name: 'edit_drop_rates')]
    public function editDropRates(
        AdminContext $context,
        Request $request,
        EntityManagerInterface $em,
        GemRepository $gemRepo,
        MobDropRateRepository $dropRateRepo,
        AdminUrlGenerator $urlGenerator,
    ): Response {
        /** @var Mob $mob */
        $mob  = $context->getEntity()->getInstance();
        $gems = $gemRepo->findAllOrdered();

        if ($request->isMethod('POST')) {
            $this->requireCsrfToken('mob_drop_rates_' . $mob->getId(), $request);

            foreach ($gems as $gem) {
                $percentage = (float) str_replace(',', '.', (string) $request->request->get('gem_' . $gem->getId(), '0'));
                $rate = $dropRateRepo->findOneBy(['mob' => $mob, 'gem' => $gem]);
                if (!$rate) {
                    $rate = (new MobDropRate())->setMob($mob)->setGem($gem);
                    $em->persist($rate);
                }
                $rate->setProbability($percentage / 100);
            }
            $em->flush();

            $this->addFlash('success', 'Taux de drop de ' . $mob->getName() . ' mis à jour.');
            $url = $urlGenerator->setController(self::class)->setAction('editDropRates')->setEntityId($mob->getId())->generateUrl();
            return $this->redirect($url);
        }

        $probabilityByGemId = [];
        foreach ($mob->getDropRates() as $rate) {
            $probabilityByGemId[$rate->getGem()->getId()] = $rate->getProbability();
        }

        return $this->render('admin/mob_drop_rates_edit.html.twig', [
            'mob'                => $mob,
            'gems'               => $gems,
            'probabilityByGemId' => $probabilityByGemId,
        ]);
    }
}
