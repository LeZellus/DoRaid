<?php

namespace App\Controller\Admin;

use App\Entity\SalleComposition;
use App\Entity\SalleCompositionMob;
use App\Repository\MobRepository;
use App\Repository\SalleCompositionMobRepository;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SalleCompositionCrudController extends AbstractCrudController
{
    use CsrfGuardTrait;

    public static function getEntityFqcn(): string
    {
        return SalleComposition::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Composition de salle')
            ->setEntityLabelInPlural('Compositions de salle')
            ->setDefaultSort(['salle' => 'ASC', 'orderNumber' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('salle', 'Salle'));
    }

    public function configureActions(Actions $actions): Actions
    {
        $editMobs = Action::new('editMobs', 'Mobs', 'fa fa-list')
            ->linkToCrudAction('editMobs');

        return $actions->add(Crud::PAGE_INDEX, $editMobs);
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('salle', 'Salle');
        yield IntegerField::new('orderNumber', 'Ordre');
        yield TextField::new('label', 'Libellé')->setRequired(false);
        yield NumberField::new('expectedScore', 'Score espéré / joueur')
            ->setNumDecimals(2)
            ->onlyOnIndex()
            ->setSortable(false);
    }

    /**
     * Édition en un seul écran des quantités de mobs d'une composition de salle, plutôt
     * qu'une ligne EasyAdmin séparée par couple composition/mob. Une quantité à 0 retire le
     * mob de la composition.
     */
    #[AdminRoute(path: '/{entityId}/mobs', name: 'edit_mobs')]
    public function editMobs(
        AdminContext $context,
        Request $request,
        EntityManagerInterface $em,
        MobRepository $mobRepo,
        SalleCompositionMobRepository $mobQuantityRepo,
        AdminUrlGenerator $urlGenerator,
    ): Response {
        /** @var SalleComposition $composition */
        $composition = $context->getEntity()->getInstance();
        $mobs = $mobRepo->findByRaidTemplateOrdered($composition->getSalle()->getRaidTemplate());

        if ($request->isMethod('POST')) {
            $this->requireCsrfToken('composition_mobs_' . $composition->getId(), $request);

            foreach ($mobs as $mob) {
                $quantity = (int) $request->request->get('mob_' . $mob->getId(), 0);
                $mobQuantity = $mobQuantityRepo->findOneBy(['composition' => $composition, 'mob' => $mob]);

                if ($quantity > 0) {
                    if (!$mobQuantity) {
                        $mobQuantity = (new SalleCompositionMob())->setComposition($composition)->setMob($mob);
                        $em->persist($mobQuantity);
                    }
                    $mobQuantity->setQuantity($quantity);
                } elseif ($mobQuantity) {
                    $em->remove($mobQuantity);
                }
            }
            $em->flush();

            $this->addFlash('success', 'Composition « ' . (string) $composition . ' » mise à jour.');
            $url = $urlGenerator->setController(self::class)->setAction('editMobs')->setEntityId($composition->getId())->generateUrl();
            return $this->redirect($url);
        }

        $quantityByMobId = [];
        foreach ($composition->getMobQuantities() as $mobQuantity) {
            $quantityByMobId[$mobQuantity->getMob()->getId()] = $mobQuantity->getQuantity();
        }

        return $this->render('admin/salle_composition_mobs_edit.html.twig', [
            'composition'     => $composition,
            'mobs'            => $mobs,
            'quantityByMobId' => $quantityByMobId,
        ]);
    }
}
