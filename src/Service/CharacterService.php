<?php

namespace App\Service;

use App\Entity\Character;
use App\Entity\MemberStatus;
use App\Exception\BusinessRuleException;
use Doctrine\ORM\EntityManagerInterface;

class CharacterService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

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
}
