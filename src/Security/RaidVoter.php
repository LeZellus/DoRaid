<?php

namespace App\Security;

use App\Entity\Raid;
use App\Entity\User;
use App\Repository\CharacterRepository;
use App\Repository\RaidParticipantRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class RaidVoter extends Voter
{
    public const CREATOR           = 'RAID_CREATOR';
    public const VIEW              = 'RAID_VIEW';
    public const INITIATIVE_MANAGE = 'RAID_INITIATIVE_MANAGE';

    public function __construct(
        private readonly CharacterRepository $charRepo,
        private readonly RaidParticipantRepository $participantRepo,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::CREATOR, self::VIEW, self::INITIATIVE_MANAGE], true) && $subject instanceof Raid;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var Raid $subject */
        $user = $token->getUser();

        return match ($attribute) {
            self::VIEW => $subject->isPublic()
                || ($user instanceof User && (
                    $subject->isCreatedBy($user)
                    || !empty($this->charRepo->findConfirmedInGuild($user, $subject->getGuild()))
                )),
            self::CREATOR => $user instanceof User && $subject->isCreatedBy($user),
            // Créateur du raid, ou membre habilité à gérer les raids de la guilde à condition
            // qu'il participe lui-même à ce raid (un statut de gestion sur la guilde ne donne pas
            // accès aux raids auxquels on ne participe pas).
            self::INITIATIVE_MANAGE => $user instanceof User && (
                $subject->isCreatedBy($user)
                || (
                    !empty($this->charRepo->findCanCreateRaidsInGuild($user, $subject->getGuild()))
                    && $this->participantRepo->findAcceptedCharacterForUserAndRaid($user, $subject) !== null
                )
            ),
        };
    }
}
