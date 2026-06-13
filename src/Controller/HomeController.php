<?php

namespace App\Controller;

use App\Repository\CharacterRepository;
use App\Service\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(CharacterRepository $characterRepo, DashboardService $dashboardService): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->render('home/index.html.twig');
        }

        if (empty($characterRepo->findByUser($user))) {
            $this->addFlash('onboarding', 'Bienvenue ! Commencez par créer votre premier personnage pour rejoindre des guildes et des raids.');
            return $this->redirectToRoute('app_character_new');
        }

        return $this->render('home/index.html.twig', $dashboardService->getDataForUser($user));
    }
}
