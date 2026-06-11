<?php

namespace App\Controller;

use App\Entity\MemberStatus;
use App\Entity\RaidParticipantStatus;
use App\Entity\RaidStatus;
use App\Repository\CharacterRepository;
use App\Repository\GuildMembershipRepository;
use App\Repository\RaidParticipantRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        RaidParticipantRepository $participantRepo,
        GuildMembershipRepository $membershipRepo,
        CharacterRepository $characterRepo,
    ): Response {
        $user = $this->getUser();

        if (!$user) {
            return $this->render('home/index.html.twig');
        }

        if (empty($characterRepo->findByUser($user))) {
            $this->addFlash('onboarding', 'Bienvenue ! Commencez par créer votre premier personnage pour rejoindre des guildes et des raids.');
            return $this->redirectToRoute('app_character_new');
        }

        $allParticipations = $participantRepo->findByUser($user);
        $allMemberships    = $membershipRepo->findByUser($user);

        $activeRaids     = [];
        $pendingRaids    = [];
        $completedRaids  = [];

        foreach ($allParticipations as $p) {
            if ($p->getStatus() === RaidParticipantStatus::Pending) {
                $pendingRaids[] = $p;
            } elseif ($p->getRaid()->getStatus() === RaidStatus::Open) {
                $activeRaids[] = $p;
            } else {
                $completedRaids[] = $p;
            }
        }

        $confirmedMemberships = [];
        $pendingMemberships   = [];

        foreach ($allMemberships as $m) {
            if ($m->getStatus() === MemberStatus::Pending) {
                $pendingMemberships[] = $m;
            } else {
                $confirmedMemberships[] = $m;
            }
        }

        return $this->render('home/index.html.twig', [
            'activeRaids'          => $activeRaids,
            'pendingRaids'         => $pendingRaids,
            'completedRaids'       => $completedRaids,
            'confirmedMemberships' => $confirmedMemberships,
            'pendingMemberships'   => $pendingMemberships,
        ]);
    }
}
