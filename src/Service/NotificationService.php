<?php

namespace App\Service;

use App\Entity\Character;
use App\Entity\Guild;
use App\Entity\GuildMembership;
use App\Entity\Notification;
use App\Entity\MemberStatus;
use App\Entity\Raid;
use App\Entity\RaidParticipant;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $router,
    ) {}

    public function notifyParticipationReceived(RaidParticipant $participant): void
    {
        $raid      = $participant->getRaid();
        $character = $participant->getCharacter();

        $this->persist(
            $raid->getCreator()->getUser(),
            'participation_pending',
            'Nouvelle candidature',
            $character->getName() . ' a candidaté pour le raid ' . $raid->getRaidTemplate()->getName() . ' (' . $raid->getGuild()->getName() . ')',
            $this->router->generate('app_raid_show', ['id' => $raid->getId()]),
        );
    }

    public function notifyParticipationAccepted(RaidParticipant $participant): void
    {
        $raid = $participant->getRaid();

        $this->persist(
            $participant->getCharacter()->getUser(),
            'participation_accepted',
            'Candidature acceptée',
            'Votre personnage ' . $participant->getCharacter()->getName() . ' a été accepté dans le raid ' . $raid->getRaidTemplate()->getName() . ' (' . $raid->getGuild()->getName() . ')',
            $this->router->generate('app_raid_show', ['id' => $raid->getId()]),
        );
    }

    public function notifyParticipationKicked(RaidParticipant $participant): void
    {
        $raid = $participant->getRaid();

        $this->persist(
            $participant->getCharacter()->getUser(),
            'participation_kicked',
            'Retiré d\'un raid',
            'Votre personnage ' . $participant->getCharacter()->getName() . ' a été retiré du raid ' . $raid->getRaidTemplate()->getName() . ' (' . $raid->getGuild()->getName() . ')',
            $this->router->generate('app_raid_show', ['id' => $raid->getId()]),
        );
    }

    public function notifyMembershipRequested(GuildMembership $membership): void
    {
        $character = $membership->getCharacter();
        $guild     = $membership->getGuild();

        foreach ($guild->getMemberships() as $m) {
            if ($m->getStatus() === MemberStatus::Leader) {
                $this->persist(
                    $m->getCharacter()->getUser(),
                    'membership_pending',
                    'Nouvelle demande d\'adhésion',
                    $character->getName() . ' demande à rejoindre ' . $guild->getName(),
                    $this->router->generate('app_guild_members', ['slug' => $guild->getSlug()]),
                );
            }
        }
    }

    public function notifyMembershipApproved(GuildMembership $membership): void
    {
        $this->persist(
            $membership->getCharacter()->getUser(),
            'membership_approved',
            'Demande acceptée',
            'Votre personnage ' . $membership->getCharacter()->getName() . ' a rejoint ' . $membership->getGuild()->getName(),
            $this->router->generate('app_guild_show', ['slug' => $membership->getGuild()->getSlug()]),
        );
    }

    public function notifyMembershipRejected(GuildMembership $membership): void
    {
        $this->persist(
            $membership->getCharacter()->getUser(),
            'membership_rejected',
            'Demande refusée',
            'La demande de ' . $membership->getCharacter()->getName() . ' pour rejoindre ' . $membership->getGuild()->getName() . ' a été refusée',
            $this->router->generate('app_guild_index'),
        );
    }

    private function persist(User $user, string $type, string $title, string $message, ?string $link = null): void
    {
        $this->em->persist(
            (new Notification())
                ->setUser($user)
                ->setType($type)
                ->setTitle($title)
                ->setMessage($message)
                ->setLink($link)
        );
    }
}
