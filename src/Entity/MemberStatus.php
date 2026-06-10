<?php

namespace App\Entity;

enum MemberStatus: string
{
    case Leader  = 'leader';
    case Member  = 'member';
    case Pending = 'pending';

    public function label(): string
    {
        return match($this) {
            self::Leader  => 'Meneur',
            self::Member  => 'Membre',
            self::Pending => 'En attente',
        };
    }
}
