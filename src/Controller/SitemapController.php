<?php

namespace App\Controller;

use App\Repository\GuildRepository;
use App\Repository\RaidRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SitemapController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'app_sitemap', defaults: ['_format' => 'xml'])]
    public function index(RaidRepository $raidRepo, GuildRepository $guildRepo): Response
    {
        $raids  = $raidRepo->findPublicForSitemap();
        $guilds = $guildRepo->findAllOrdered();

        $response = new Response(
            $this->renderView('sitemap.xml.twig', [
                'raids'  => $raids,
                'guilds' => $guilds,
            ]),
            200,
            ['Content-Type' => 'application/xml']
        );

        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
