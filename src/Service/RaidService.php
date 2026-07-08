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
use App\Repository\GuildMembershipRepository;
use App\Repository\RaidCommentRepository;
use App\Repository\RaidParticipantRepository;
use App\Repository\RaidRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Workflow\WorkflowInterface;

class RaidService
{
    public function __construct(
        private readonly CharacterRepository $charRepo,
        private readonly RaidRepository $raidRepo,
        private readonly RaidParticipantRepository $participantRepo,
        private readonly RaidCommentRepository $commentRepo,
        private readonly GuildMembershipRepository $membershipRepo,
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
        $membership = $character->getMembership();
        if ($membership === null
            || (int) $membership->getGuild()->getId() !== (int) $guild->getId()
            || !$membership->canCreateRaids()
        ) {
            throw new BusinessRuleException('Ce personnage n\'a pas la permission de créer un raid pour cette guilde.');
        }

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
        if ($participant->getRaid()->getStatus() !== RaidStatus::Open) {
            throw new BusinessRuleException('Ce raid est terminé, impossible d\'accepter un participant.');
        }
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

        $this->discord->notifyParticipationAccepted($participant);
    }

    /** Remet un participant accepté dans la liste des intéressés (ex. désistement géré par le créateur). */
    public function moveToWaitlist(RaidParticipant $participant): void
    {
        try {
            $this->participantWorkflow->apply($participant, 'unaccept');
        } catch (\Symfony\Component\Workflow\Exception\NotEnabledTransitionException) {
            throw new BusinessRuleException('Ce participant n\'est pas accepté.');
        }
        $participant->setGroup(null);
        $this->notificationService->notifyParticipationMovedToWaitlist($participant);
        $this->em->flush();
    }

    /** Déplace un participant (accepté ou intéressé) vers un autre raid de la même guilde. */
    public function moveParticipant(RaidParticipant $participant, Raid $targetRaid): void
    {
        $sourceRaid = $participant->getRaid();

        if ($sourceRaid->getStatus() !== RaidStatus::Open) {
            throw new BusinessRuleException('Ce raid est terminé, impossible d\'en déplacer un participant.');
        }
        if ($sourceRaid->getId() === $targetRaid->getId()) {
            throw new BusinessRuleException('Ce participant est déjà sur ce raid.');
        }
        if ($sourceRaid->getGuild()->getId() !== $targetRaid->getGuild()->getId()) {
            throw new BusinessRuleException('Le raid de destination doit appartenir à la même guilde.');
        }
        if ($targetRaid->getStatus() !== RaidStatus::Open) {
            throw new BusinessRuleException('Le raid de destination est terminé.');
        }
        if ($targetRaid->isParticipant($participant->getCharacter())) {
            throw new BusinessRuleException('Ce personnage participe déjà au raid de destination.');
        }
        if ($participant->getStatus() === RaidParticipantStatus::Accepted && $targetRaid->isFull()) {
            throw new BusinessRuleException('Le raid de destination est complet.');
        }

        $participant->setRaid($targetRaid);
        $participant->setGroup(null);
        $this->notificationService->notifyParticipantMoved($participant, $sourceRaid);
        $this->em->flush();
    }

    /** Transfère la création du raid à un autre participant accepté (ex. absence du créateur initial). */
    public function transferCreator(Raid $raid, Character $newCreator): void
    {
        if ($raid->getCreator() === $newCreator || $raid->getCreator()->getId() === $newCreator->getId()) {
            throw new BusinessRuleException('Ce personnage est déjà créateur du raid.');
        }
        if (!$raid->isAcceptedParticipant($newCreator)) {
            throw new BusinessRuleException('Seul un participant accepté peut devenir créateur du raid.');
        }

        $raid->setCreator($newCreator);
        $this->notificationService->notifyRaidCreatorTransferred($raid, $newCreator);
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

    public function cancelParticipation(RaidParticipant $participant, User $user): void
    {
        if ((int) $participant->getCharacter()->getUser()->getId() !== (int) $user->getId()) {
            throw new BusinessRuleException('Vous ne pouvez pas annuler la participation d\'un autre joueur.');
        }

        $creator = $participant->getRaid()->getCreator();
        if ($creator->getId() === $participant->getCharacter()->getId()) {
            throw new BusinessRuleException('Le créateur du raid ne peut pas se retirer. Transférez d\'abord le rôle de créateur.');
        }

        if ($participant->getStatus() === RaidParticipantStatus::Accepted) {
            $this->notificationService->notifyParticipationCancelledByUser($participant);
        }

        $this->em->remove($participant);
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

    /** Assemble les raids groupés/filtrés et les données associées pour la liste des raids. */
    public function buildIndexData(
        ?User $user,
        ?string $serverName,
        ?string $filterType,
        bool $filterNotFull,
        bool $filterSoon,
    ): array {
        $userGuildIds = $user
            ? array_map(fn($m) => $m->getGuild()->getId(), $this->membershipRepo->findConfirmedForUser($user))
            : [];

        $grouped = $this->raidRepo->findGroupedOpen($userGuildIds, $serverName, $filterSoon);

        $upcomingRaids = $grouped['upcoming'];
        // Un raid "démarré" peut avoir dépassé sa durée planifiée depuis la dernière
        // exécution de app:close-expired-raids : on le clôture à la volée plutôt que
        // de l'afficher comme encore ouvert.
        $startedRaids = array_values(array_filter(
            $grouped['started'],
            fn($r) => !$this->closeIfExpired($r)
        ));
        $ongoingRaids = $grouped['ongoing'];

        // Collect available types from all open raids before applying the type filter
        $raidTypes = array_unique(array_map(
            fn($r) => $r->getRaidTemplate()->getName(),
            array_merge($upcomingRaids, $startedRaids, $ongoingRaids)
        ));
        sort($raidTypes);

        if ($filterType) {
            $byType        = fn($r) => $r->getRaidTemplate()->getName() === $filterType;
            $upcomingRaids = array_values(array_filter($upcomingRaids, $byType));
            $startedRaids  = array_values(array_filter($startedRaids,  $byType));
            $ongoingRaids  = array_values(array_filter($ongoingRaids,  $byType));
        }

        if ($filterNotFull) {
            $notFull       = fn($r) => !$r->isFull();
            $upcomingRaids = array_values(array_filter($upcomingRaids, $notFull));
            $startedRaids  = array_values(array_filter($startedRaids,  $notFull));
            $ongoingRaids  = array_values(array_filter($ongoingRaids,  $notFull));
        }

        return [
            'upcomingRaids'    => $upcomingRaids,
            'startedRaids'     => $startedRaids,
            'ongoingRaids'     => $ongoingRaids,
            'raidTypes'        => $raidTypes,
            'userGuildIds'     => $userGuildIds,
            'myParticipations' => $user ? $this->participantRepo->findOpenParticipationsForUser($user) : [],
        ];
    }

    /** Assemble les données de candidatures/participants pour la page de détail d'un raid. */
    public function buildShowData(Raid $raid, ?User $user): array
    {
        $userId    = $user ? (int) $user->getId() : null;
        $eligible  = $user ? $this->charRepo->findAllEligibleForRaid($user, $raid) : [];
        $isCreator = $user && $raid->isCreatedBy($user);

        $acceptedCharacters  = [];
        $pendingApplications = [];
        $participantsByUser  = [];
        $myParticipants      = [];
        foreach ($raid->getParticipants() as $p) {
            $participantsByUser[(int) $p->getCharacter()->getUser()->getId()] = $p->getCharacter();
            if ($userId && (int) $p->getCharacter()->getUser()->getId() === $userId) {
                $myParticipants[] = $p;
                if ($p->getStatus() === RaidParticipantStatus::Accepted) {
                    $acceptedCharacters[] = $p->getCharacter();
                } else {
                    $pendingApplications[] = $p;
                }
            }
        }

        $characterIds = array_values(array_filter(
            array_map(fn($p) => $p->getCharacter()->getId(), iterator_to_array($raid->getParticipants())),
            fn($id) => $id !== null
        ));

        $canManageRaids      = $user && !empty($this->charRepo->findCanCreateRaidsInGuild($user, $raid->getGuild()));
        $canManageNotes      = $isCreator || $canManageRaids;
        // Un membre habilité à gérer les raids ne peut moduler l'initiative que s'il participe
        // lui-même à ce raid (cf. RaidVoter::INITIATIVE_MANAGE).
        $canManageInitiative = $isCreator || ($canManageRaids && !empty($acceptedCharacters));

        return [
            'eligible'            => $eligible,
            'isCreator'           => $isCreator,
            'canManageNotes'      => $canManageNotes,
            'canManageInitiative' => $canManageInitiative,
            'isLeader'            => $user && $raid->getGuild()->isLeaderOf($user),
            'acceptedCharacters'  => $acceptedCharacters,
            'pendingApplications' => $pendingApplications,
            'myParticipants'      => $myParticipants,
            'participantsByUser'  => $participantsByUser,
            'rootComments'        => $this->commentRepo->findRootByRaid($raid),
            'myOtherRaids'        => $isCreator ? $this->raidRepo->findOpenByGuild($raid->getGuild(), $raid) : [],
            'completedRaidCounts' => $this->participantRepo->countCompletedByCharacters($characterIds),
        ];
    }
}
