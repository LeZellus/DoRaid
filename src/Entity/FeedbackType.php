<?php

namespace App\Entity;

enum FeedbackType: string
{
    case Bug        = 'bug';
    case Suggestion = 'suggestion';

    public function label(): string
    {
        return match($this) {
            self::Bug        => 'Bug',
            self::Suggestion => 'Suggestion',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::Bug        => '🐛',
            self::Suggestion => '💡',
        };
    }
}
