<?php

namespace App\Controller;

use App\Entity\RaidTemplate;
use App\Repository\GuildRepository;
use App\Repository\RaidRepository;
use App\Repository\RaidTemplateRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

class SitemapController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    #[Route('/sitemap.xml', name: 'app_sitemap', defaults: ['_format' => 'xml'])]
    public function index(RaidRepository $raidRepo, GuildRepository $guildRepo, RaidTemplateRepository $templateRepo): Response
    {
        $raids  = $raidRepo->findPublicForSitemap();
        $guilds = $guildRepo->findAllOrdered();

        $slugger = new AsciiSlugger();
        $templatesWithGuide = array_values(array_filter(
            $templateRepo->findAllOrdered(),
            fn (RaidTemplate $t) => is_file(
                $this->projectDir . '/config/raid_guides/' . $slugger->slug($t->getName())->lower()->toString() . '.json'
            ),
        ));

        $response = new Response(
            $this->renderView('sitemap.xml.twig', [
                'raids'              => $raids,
                'guilds'             => $guilds,
                'templatesWithGuide' => $templatesWithGuide,
            ]),
            200,
            ['Content-Type' => 'application/xml']
        );

        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
