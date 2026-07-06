<?php

namespace App\Service;

use App\Entity\InitiativeModifier;
use App\Entity\Raid;
use App\Entity\RaidGroup;
use App\Entity\RaidParticipant;
use App\Entity\RaidParticipantStatus;
use App\Entity\RaidStatus;
use App\Exception\BusinessRuleException;
use App\Repository\RaidGroupRepository;
use Doctrine\ORM\EntityManagerInterface;

class RaidGroupService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RaidGroupRepository $groupRepo,
    ) {}

    public function createGroup(Raid $raid): RaidGroup
    {
        $this->assertOpen($raid);

        $group = (new RaidGroup())->setRaid($raid)->setPosition($this->groupRepo->nextPosition($raid));
        $this->em->persist($group);
        $this->em->flush();

        return $group;
    }

    public function renameGroup(RaidGroup $group, ?string $label): void
    {
        $this->assertOpen($group->getRaid());

        $label = $label !== null ? trim($label) : null;
        if ($label !== null && mb_strlen($label) > 50) {
            throw new BusinessRuleException('Le nom du groupe ne peut pas dépasser 50 caractères.');
        }
        $group->setLabel($label === '' ? null : $label);
        $this->em->flush();
    }

    public function deleteGroup(RaidGroup $group): void
    {
        $this->assertOpen($group->getRaid());

        foreach ($group->getParticipants() as $participant) {
            $participant->setGroup(null);
        }

        $this->em->remove($group);
        $this->em->flush();
    }

    public function assignParticipant(RaidParticipant $participant, ?RaidGroup $group): void
    {
        $raid = $participant->getRaid();
        $this->assertOpen($raid);

        if ($participant->getStatus() !== RaidParticipantStatus::Accepted) {
            throw new BusinessRuleException('Seuls les participants acceptés peuvent être placés dans un groupe.');
        }

        if ($group !== null) {
            if ($group->getRaid()->getId() !== $raid->getId()) {
                throw new BusinessRuleException('Ce groupe n\'appartient pas à ce raid.');
            }
            if ($group->isFull() && $participant->getGroup() !== $group) {
                throw new BusinessRuleException(
                    'Ce groupe est déjà complet (' . RaidGroup::MAX_MEMBERS . '/' . RaidGroup::MAX_MEMBERS . ').'
                );
            }
        }

        $participant->setGroup($group);
        $this->em->flush();
    }

    public function toggleInitiativeModifier(RaidParticipant $participant, InitiativeModifier $modifier): void
    {
        $this->assertOpen($participant->getRaid());

        if ($participant->getStatus() !== RaidParticipantStatus::Accepted) {
            throw new BusinessRuleException('Seuls les participants acceptés peuvent avoir un modificateur d\'initiative.');
        }

        $participant->toggleInitiativeModifier($modifier);
        $this->em->flush();
    }

    private function assertOpen(Raid $raid): void
    {
        if ($raid->getStatus() !== RaidStatus::Open) {
            throw new BusinessRuleException('Ce raid est terminé, les groupes ne sont plus modifiables.');
        }
    }
}
