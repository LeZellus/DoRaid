<?php

namespace App\Controller;

use App\Repository\CharacterRepository;
use App\Repository\GuildRepository;
use App\Repository\RaidRepository;
use App\Repository\UserRepository;
use App\Service\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        CharacterRepository $characterRepo,
        DashboardService $dashboardService,
        UserRepository $userRepo,
        RaidRepository $raidRepo,
        GuildRepository $guildRepo,
    ): Response {
        $user = $this->getUser();

        if (!$user) {
            return $this->render('home/index.html.twig', [
                'platformStats' => [
                    'users'  => $userRepo->count([]),
                    'raids'  => $raidRepo->count([]),
                    'guilds' => $guildRepo->count([]),
                ],
            ]);
        }

        if (empty($characterRepo->findByUser($user))) {
            $this->addFlash('onboarding', 'Bienvenue ! Commencez par créer votre premier personnage pour rejoindre des guildes et des raids.');
            return $this->redirectToRoute('app_character_new');
        }

        return $this->render('home/index.html.twig', $dashboardService->getDataForUser($user));
    }
}
