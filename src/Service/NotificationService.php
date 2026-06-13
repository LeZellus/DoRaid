<?php

namespace App\Service;

use App\Entity\Character;
use App\Entity\Notification;
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
