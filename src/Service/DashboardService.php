<?php

namespace App\Service;

use App\Entity\MemberStatus;
use App\Entity\RaidParticipantStatus;
use App\Entity\RaidStatus;
use App\Entity\User;
use App\Repository\GuildMembershipRepository;
use App\Repository\RaidParticipantRepository;
use App\Repository\RaidRepository;

class DashboardService
{
    public function __construct(
        private readonly RaidParticipantRepository $participantRepo,
        private readonly GuildMembershipRepository $membershipRepo,
        private readonly RaidRepository $raidRepo,
    ) {}

    public function getDataForUser(User $user): array
    {
        $activeRaids    = [];
        $pendingRaids   = [];
        $completedRaids = [];

        foreach ($this->participantRepo->findByUser($user) as $p) {
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

        foreach ($this->membershipRepo->findByUser($user) as $m) {
            if ($m->getStatus() === MemberStatus::Pending) {
                $pendingMemberships[] = $m;
                continue;
            }

            $confirmedMemberships[] = $m;
            $guildId = $m->getGuild()->getId();
            $guildMap[$guildId] ??= ['guild' => $m->getGuild(), 'isLeader' => false, 'count' => 0];
            $guildMap[$guildId]['count']++;

            if ($m->getStatus() === MemberStatus::Leader) {
                $guildMap[$guildId]['isLeader'] = true;
            }
        }

        usort($guildMap, fn($a, $b) =>
            $b['isLeader'] <=> $a['isLeader'] ?: strcmp($a['guild']->getName(), $b['guild']->getName())
        );

        $pendingByGuild = [];
        foreach ($this->membershipRepo->findPendingForLeader($user) as $m) {
            $id = $m->getGuild()->getId();
            $pendingByGuild[$id] ??= ['guild' => $m->getGuild(), 'count' => 0];
            $pendingByGuild[$id]['count']++;
        }

        $pendingByRaid = [];
        foreach ($this->participantRepo->findPendingForCreator($user) as $p) {
            $id = $p->getRaid()->getId();
            $pendingByRaid[$id] ??= ['raid' => $p->getRaid(), 'count' => 0];
            $pendingByRaid[$id]['count']++;
        }

        $userGuildIds = array_map(fn($m) => $m->getGuild()->getId(), $confirmedMemberships);

        return [
            'activeRaids'          => $activeRaids,
            'pendingRaids'         => $pendingRaids,
            'completedRaids'       => $completedRaids,
            'confirmedMemberships' => $confirmedMemberships,
            'guildMap'             => $guildMap,
            'pendingMemberships'   => $pendingMemberships,
            'pendingByGuild'       => $pendingByGuild,
            'pendingByRaid'        => $pendingByRaid,
            'publicRaids'          => $this->raidRepo->findPublicOpen($userGuildIds),
        ];
    }
}
