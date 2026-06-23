<?php

namespace App\Service;

use App\Entity\Character;
use App\Entity\MemberStatus;
use App\Exception\BusinessRuleException;
use App\Repository\EnigmeCommentRepository;
use App\Repository\EnigmeImageRepository;
use App\Repository\RaidParticipantRepository;
use Doctrine\ORM\EntityManagerInterface;

class CharacterService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RaidParticipantRepository $raidParticipantRepo,
        private readonly EnigmeCommentRepository $enigmeCommentRepo,
        private readonly EnigmeImageRepository $enigmeImageRepo,
    ) {}

    public function deleteCharacter(Character $character): void
    {
        $membership = $character->getMembership();
        if ($membership !== null && $membership->getStatus() === MemberStatus::Leader) {
            throw new BusinessRuleException(
                'Ce personnage est meneur de « ' . $membership->getGuild()->getName() . ' ». Supprimez la guilde avant de supprimer ce personnage.'
            );
        }

        $this->em->remove($character);
        $this->em->flush();
    }

    /**
     * Purge toute trace d'appartenance liée à l'ancien serveur (guilde, candidatures/participations
     * aux raids, commentaires et images d'énigmes) avant un changement de serveur.
     * Précondition : $character->getServer() est déjà mis à jour avec le nouveau serveur, non flush.
     */
    public function clearParticipationsForServerChange(Character $character): void
    {
        $membership = $character->getMembership();
        if ($membership !== null && $membership->getStatus() === MemberStatus::Leader) {
            throw new BusinessRuleException(
                'Ce personnage est meneur de « ' . $membership->getGuild()->getName() . ' ». Transférez le rôle de meneur avant de changer de serveur.'
            );
        }

        if ($membership !== null) {
            $this->em->remove($membership);
        }

        foreach ($this->raidParticipantRepo->findBy(['character' => $character]) as $participant) {
            $this->em->remove($participant);
        }

        foreach ($this->enigmeCommentRepo->findBy(['author' => $character]) as $comment) {
            $this->em->remove($comment);
        }

        foreach ($this->enigmeImageRepo->findBy(['addedBy' => $character]) as $image) {
            $this->em->remove($image);
        }
    }
}
