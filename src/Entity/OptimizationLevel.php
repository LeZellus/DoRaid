<?php

namespace App\Entity;

enum OptimizationLevel: string
{
    case Low    = 'bas';
    case Medium = 'moyen';
    case High   = 'haute';

    public function label(): string
    {
        return match($this) {
            self::Low    => 'Bas',
            self::Medium => 'Moyen',
            self::High   => 'Haute',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Low    => 'default',
            self::Medium => 'warning',
            self::High   => 'success',
        };
    }
}
