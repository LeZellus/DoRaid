<?php

namespace App\Controller;

use App\Entity\MemberStatus;
use App\Entity\RaidParticipantStatus;
use App\Entity\RaidStatus;
use App\Repository\CharacterRepository;
use App\Repository\GuildMembershipRepository;
use App\Repository\RaidParticipantRepository;
use App\Repository\RaidRepository;
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
        RaidRepository $raidRepo,
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
        $guildMap             = [];

        foreach ($allMemberships as $m) {
            if ($m->getStatus() === MemberStatus::Pending) {
                $pendingMemberships[] = $m;
            } else {
                $confirmedMemberships[] = $m;
                $guildId = $m->getGuild()->getId();
                if (!isset($guildMap[$guildId])) {
                    $guildMap[$guildId] = [
                        'guild'    => $m->getGuild(),
                        'isLeader' => false,
                        'count'    => 0,
                    ];
                }
                $guildMap[$guildId]['count']++;
                if ($m->getStatus() === MemberStatus::Leader) {
                    $guildMap[$guildId]['isLeader'] = true;
                }
            }
        }

        // Leaders first, then alphabetical
        usort($guildMap, fn($a, $b) =>
            $b['isLeader'] <=> $a['isLeader'] ?: strcmp($a['guild']->getName(), $b['guild']->getName())
        );

        $pendingByGuild = [];
        foreach ($membershipRepo->findPendingForLeader($user) as $m) {
            $id = $m->getGuild()->getId();
            $pendingByGuild[$id] ??= ['guild' => $m->getGuild(), 'count' => 0];
            $pendingByGuild[$id]['count']++;
        }

        $pendingByRaid = [];
        foreach ($participantRepo->findPendingForCreator($user) as $p) {
            $id = $p->getRaid()->getId();
            $pendingByRaid[$id] ??= ['raid' => $p->getRaid(), 'count' => 0];
            $pendingByRaid[$id]['count']++;
        }

        $userGuildIds = array_map(fn($m) => $m->getGuild()->getId(), $confirmedMemberships);
        $publicRaids  = $raidRepo->findPublicOpen($userGuildIds);

        return $this->render('home/index.html.twig', [
            'activeRaids'          => $activeRaids,
            'pendingRaids'         => $pendingRaids,
            'completedRaids'       => $completedRaids,
            'confirmedMemberships' => $confirmedMemberships,
            'guildMap'             => $guildMap,
            'pendingMemberships'   => $pendingMemberships,
            'pendingByGuild'       => $pendingByGuild,
            'pendingByRaid'        => $pendingByRaid,
            'publicRaids'          => $publicRaids,
        ]);
    }
}
