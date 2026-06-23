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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Workflow\WorkflowInterface;

class RaidService
{
    public function __construct(
        private readonly CharacterRepository $charRepo,
        private readonly EnigmeSyncService $enigmeSyncService,
        private readonly DiscordNotifier $discord,
        private readonly NotificationService $notificationService,
        private readonly EntityManagerInterface $em,
        #[Autowire(service: 'state_machine.raid_status')]
        private readonly WorkflowInterface $raidWorkflow,
        #[Autowire(service: 'state_machine.raid_participant_status')]
        private readonly WorkflowInterface $participantWorkflow,
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

        // Le raid plein reste ouvert aux candidatures : elles alimentent la liste des
        // intéressés, dans laquelle le créateur peut piocher en cas de désistement.
        $participant = (new RaidParticipant())->setRaid($raid)->setCharacter($character);
        $this->em->persist($participant);
        $this->notificationService->notifyParticipationReceived($participant);
        $this->em->flush();

        $this->discord->notifyParticipationReceived($participant);
    }

    public function acceptParticipant(RaidParticipant $participant): void
    {
        if ($participant->getRaid()->isFull()) {
            throw new BusinessRuleException('Le raid est complet. Retirez un participant avant d\'en accepter un nouveau.');
        }

        try {
            $this->participantWorkflow->apply($participant, 'accept');
        } catch (\Symfony\Component\Workflow\Exception\NotEnabledTransitionException) {
            throw new BusinessRuleException('Ce participant ne peut pas être accepté dans son état actuel.');
        }
        $this->notificationService->notifyParticipationAccepted($participant);
        $this->em->flush();
    }

    public function kickParticipant(RaidParticipant $participant): void
    {
        $this->notificationService->notifyParticipationKicked($participant);
        $this->em->remove($participant);
        $this->em->flush();
    }

    public function closeRaid(Raid $raid): void
    {
        try {
            $this->raidWorkflow->apply($raid, 'close');
        } catch (\Symfony\Component\Workflow\Exception\NotEnabledTransitionException) {
            throw new BusinessRuleException('Ce raid est déjà terminé.');
        }
        $this->em->flush();
    }

    /**
     * Clôture automatiquement un raid ouvert dont la durée planifiée est dépassée.
     * Sans effet sur les raids déjà clos, sans date planifiée ou sans durée de template.
     *
     * @return bool true si le raid vient d'être clôturé.
     */
    public function closeIfExpired(Raid $raid): bool
    {
        if ($raid->getStatus() !== RaidStatus::Open) {
            return false;
        }

        $end = $raid->getExpectedEndTime();
        if ($end === null || $end > new \DateTimeImmutable()) {
            return false;
        }

        $this->raidWorkflow->apply($raid, 'close');
        $this->em->flush();

        return true;
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
