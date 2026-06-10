<?php

namespace App\Entity;

enum RaidStatus: string
{
    case Open   = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match($this) {
            self::Open   => 'Ouvert',
            self::Closed => 'Terminé',
        };
    }
}
