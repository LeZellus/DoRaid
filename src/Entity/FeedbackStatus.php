<?php

namespace App\Entity;

enum FeedbackStatus: string
{
    case Open       = 'open';
    case InProgress = 'in_progress';
    case Done       = 'done';
    case Rejected   = 'rejected';

    public function label(): string
    {
        return match($this) {
            self::Open       => 'Ouvert',
            self::InProgress => 'En cours',
            self::Done       => 'Résolu',
            self::Rejected   => 'Rejeté',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Open       => 'yellow',
            self::InProgress => 'emerald',
            self::Done       => 'success',
            self::Rejected   => 'danger',
        };
    }
}
