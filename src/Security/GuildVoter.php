<?php

namespace App\Security;

use App\Entity\Guild;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class GuildVoter extends Voter
{
    public const LEADER = 'GUILD_LEADER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::LEADER && $subject instanceof Guild;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        /** @var Guild $subject */
        return $subject->isLeaderOf($user);
    }
}
