<?php

namespace App\Security;

use App\Entity\Raid;
use App\Entity\User;
use App\Repository\CharacterRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class RaidVoter extends Voter
{
    public const CREATOR = 'RAID_CREATOR';
    public const VIEW    = 'RAID_VIEW';

    public function __construct(private readonly CharacterRepository $charRepo) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::CREATOR, self::VIEW], true) && $subject instanceof Raid;
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
        };
    }
}
