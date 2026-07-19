<?php

namespace App\Controller\Admin;

use App\Entity\Salle;
use App\Entity\SalleImage;
use App\Service\FileUploadService;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SalleCrudController extends AbstractCrudController
{
    use CsrfGuardTrait;

    public static function getEntityFqcn(): string
    {
        return Salle::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Salle')
            ->setEntityLabelInPlural('Salles')
            ->setDefaultSort(['raidTemplate' => 'ASC', 'orderNumber' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('raidTemplate', 'Type de raid'));
    }

    public function configureActions(Actions $actions): Actions
    {
        $editImages = Action::new('editImages', 'Images', 'fa fa-images')
            ->linkToCrudAction('editImages');

        return $actions->add(Crud::PAGE_INDEX, $editImages);
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('raidTemplate', 'Type de raid');
        yield IntegerField::new('orderNumber', 'Ordre');
        yield TextField::new('name', 'Nom');
        yield IntegerField::new('levelMin', 'Niveau min.');
        yield IntegerField::new('levelMax', 'Niveau max.');
    }

    /**
     * Gestion des images d'une salle (upload multiple + suppression), réservée au back-office :
     * ces images ne sont pas exposées au répartiteur de loot côté front pour l'instant.
     */
    #[AdminRoute(path: '/{entityId}/images', name: 'edit_images')]
    public function editImages(
        AdminContext $context,
        Request $request,
        EntityManagerInterface $em,
        FileUploadService $fileUpload,
        AdminUrlGenerator $urlGenerator,
        #[Autowire('%kernel.project_dir%')] string $projectDir,
    ): Response {
        /** @var Salle $salle */
        $salle = $context->getEntity()->getInstance();
        $uploadDir = $projectDir . '/public/uploads/salles/' . $salle->getId();
        $redirectUrl = $urlGenerator->setController(self::class)->setAction('editImages')->setEntityId($salle->getId())->generateUrl();

        if ($request->isMethod('POST')) {
            $this->requireCsrfToken('salle_images_' . $salle->getId(), $request);

            $deleteId = $request->request->get('delete_image');
            if ($deleteId !== null) {
                foreach ($salle->getImages() as $image) {
                    if ($image->getId() === (int) $deleteId) {
                        $this->deleteImageFile($uploadDir, $image);
                        $em->remove($image);
                        $em->flush();
                        $this->addFlash('success', 'Image supprimée.');
                        break;
                    }
                }

                return $this->redirect($redirectUrl);
            }

            /** @var UploadedFile[] $files */
            $files = $request->files->get('images') ?? [];
            foreach ($files as $file) {
                if (!$file instanceof UploadedFile || !$file->isValid()) {
                    continue;
                }
                $filename = $fileUpload->upload($file, $uploadDir, uniqid('img_', true));
                $em->persist((new SalleImage())->setSalle($salle)->setFilePath($filename));
            }
            $em->flush();

            $this->addFlash('success', 'Images de « ' . (string) $salle . ' » mises à jour.');

            return $this->redirect($redirectUrl);
        }

        return $this->render('admin/salle_images_edit.html.twig', [
            'salle' => $salle,
        ]);
    }

    private function deleteImageFile(string $uploadDir, SalleImage $image): void
    {
        $path = $uploadDir . '/' . $image->getFilePath();
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
