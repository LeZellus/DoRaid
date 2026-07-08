<?php

namespace App\Entity;

/**
 * Modificateurs temporaires d'initiative pour le Gigalodon (brandades, Dofus Cauchemar) —
 * cumulables, propres à un raid, sans jamais toucher à Character::$initiative.
 */
enum InitiativeModifier: string
{
    case Stimulante             = 'stimulante';
    case Energisante            = 'energisante';
    case Exaltante              = 'exaltante';
    case Fatiguante             = 'fatiguante';
    case Ereintante             = 'ereintante';
    case Epuisante              = 'epuisante';
    case Cauchemar              = 'cauchemard';
    case Shake                  = 'shake';
    case ShakeHauteFermentation = 'shake_haute_fermentation';

    public function points(): int
    {
        return match ($this) {
            self::Stimulante             => 100,
            self::Energisante            => 200,
            self::Exaltante              => 500,
            self::Fatiguante             => -100,
            self::Ereintante             => -200,
            self::Epuisante              => -500,
            self::Cauchemar              => 1000,
            self::Shake                  => -200,
            self::ShakeHauteFermentation => -400,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Stimulante             => 'Brandade stimulante (+100)',
            self::Energisante            => 'Brandade énergisante (+200)',
            self::Exaltante              => 'Brandade exaltante (+500)',
            self::Fatiguante             => 'Brandade fatigante (-100)',
            self::Ereintante             => 'Brandade éreintante (-200)',
            self::Epuisante              => 'Brandade épuisante (-500)',
            self::Cauchemar              => 'Dofus Cauchemar (+1000)',
            self::Shake                  => 'Shaké (-200)',
            self::ShakeHauteFermentation => 'Shaké haute fermentation (-400)',
        };
    }

    /** Chemin relatif (sous public/uploads/) de l'icône, à passer à asset(). */
    public function icon(): string
    {
        // Les deux shakés partagent la même image.
        $file = match ($this) {
            self::ShakeHauteFermentation => 'shake',
            default => $this->value,
        };
        return 'uploads/divers/' . $file . '.png';
    }
}
