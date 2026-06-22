<?php

namespace App\Controller;

use App\Entity\RaidTemplate;
use App\Repository\RaidTemplateRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

class RaidGuideController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    #[Route('/guides', name: 'app_raid_guide_index')]
    public function index(RaidTemplateRepository $repo): Response
    {
        $slugger = new AsciiSlugger();
        $templates = array_values(array_filter(
            $repo->findAllOrdered(),
            fn (RaidTemplate $t) => is_file(
                $this->projectDir . '/config/raid_guides/' . $slugger->slug($t->getName())->lower()->toString() . '.json'
            ),
        ));

        return $this->render('raid_guide/index.html.twig', ['templates' => $templates]);
    }

    #[Route('/types-de-raid/{id}/guide', name: 'app_raid_template_guide')]
    public function show(RaidTemplate $raidTemplate): Response
    {
        $slug = (new AsciiSlugger())->slug($raidTemplate->getName())->lower()->toString();
        $path = $this->projectDir . '/config/raid_guides/' . $slug . '.json';

        if (!is_file($path)) {
            throw new NotFoundHttpException('Aucun guide disponible pour ce raid.');
        }

        $guide = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return $this->render('raid_guide/show.html.twig', [
            'raidTemplate' => $raidTemplate,
            'guide' => $guide,
            'imageBasePath' => 'uploads/raid-guides/' . $slug . '/',
        ]);
    }
}
