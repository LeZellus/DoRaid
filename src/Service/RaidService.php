<?php

namespace App\Service;

use App\Entity\Character;
use App\Entity\Guild;
use App\Entity\Raid;
use App\Entity\RaidParticipant;
use App\Entity\RaidParticipantStatus;
use App\Entity\RaidStatus;
use App\Entity\RaidTemplate;
use App\Entity\User;
use App\Exception\BusinessRuleException;
use App\Repository\CharacterRepository;
use Doctrine\ORM\EntityManagerInterface;

class RaidService
{
    public function __construct(
        private readonly CharacterRepository $charRepo,
        private readonly EnigmeSyncService $enigmeSyncService,
        private readonly DiscordNotifier $discord,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Crée un raid et ajoute le créateur comme participant accepté.
     * Précondition : le contrôleur a vérifié que $character appartient à l'utilisateur courant.
     */
    public function createRaid(Raid $raid, Guild $guild, Character $character, RaidTemplate $template): void
    {
        $raid->setGuild($guild)->setCreator($character)->setRaidTemplate($template);
        $this->em->persist($raid);
        $this->em->persist(
            (new RaidParticipant())->setRaid($raid)->setCharacter($character)->setStatus(RaidParticipantStatus::Accepted)
        );
        $this->enigmeSyncService->syncFromTemplate($raid, $this->em);
        $this->em->flush();

        $this->discord->notifyRaidCreated($raid);
    }

    /**
     * Candidate un personnage à un raid.
     * Précondition : le contrôleur a vérifié que $character appartient à $user.
     */
    public function applyToRaid(Raid $raid, Character $character, User $user): void
    {
        if (!$raid->isPublic()) {
            $isMember = !empty($this->charRepo->findConfirmedInGuild($user, $raid->getGuild()));
            if (!$isMember) {
                throw new BusinessRuleException('Ce raid est privé et réservé aux membres de la guilde.');
            }
        }

        if ($raid->getStatus() !== RaidStatus::Open) {
            throw new BusinessRuleException('Ce raid est terminé.');
        }

        if ((int) $character->getServer()->getId() !== (int) $raid->getGuild()->getServer()->getId()) {
            throw new BusinessRuleException('Ce personnage n\'est pas sur le même serveur que ce raid.');
        }

        if ($raid->isParticipant($character)) {
            throw new BusinessRuleException('Ce personnage a déjà candidaté à ce raid.');
        }

        if ($raid->isFull()) {
            throw new BusinessRuleException('Ce raid est complet.');
        }

        $this->em->persist((new RaidParticipant())->setRaid($raid)->setCharacter($character));
        $this->em->flush();
    }

    public function acceptParticipant(RaidParticipant $participant): void
    {
        $participant->setStatus(RaidParticipantStatus::Accepted);
        $this->em->flush();
    }

    public function kickParticipant(RaidParticipant $participant): void
    {
        $this->em->remove($participant);
        $this->em->flush();
    }

    public function closeRaid(Raid $raid): void
    {
        $raid->setStatus(RaidStatus::Closed);
        $this->em->flush();
    }

    public function deleteRaid(Raid $raid): void
    {
        $this->em->remove($raid);
        $this->em->flush();
    }

    /** Synchronise les énigmes depuis le template si nécessaire. */
    public function syncEnigmes(Raid $raid): void
    {
        if ($this->enigmeSyncService->syncFromTemplate($raid, $this->em)) {
            $this->em->flush();
        }
    }
}
