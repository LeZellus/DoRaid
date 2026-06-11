<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    private const FR_DAYS = ['Sunday' => 'Dimanche', 'Monday' => 'Lundi', 'Tuesday' => 'Mardi',
        'Wednesday' => 'Mercredi', 'Thursday' => 'Jeudi', 'Friday' => 'Vendredi', 'Saturday' => 'Samedi'];

    private const FR_MONTHS = ['January' => 'janvier', 'February' => 'février', 'March' => 'mars',
        'April' => 'avril', 'May' => 'mai', 'June' => 'juin', 'July' => 'juillet',
        'August' => 'août', 'September' => 'septembre', 'October' => 'octobre',
        'November' => 'novembre', 'December' => 'décembre'];

    public function getFilters(): array
    {
        return [
            new TwigFilter('date_fr', $this->dateFr(...)),
        ];
    }

    public function dateFr(\DateTimeInterface|string $date): string
    {
        $dt = is_string($date) ? new \DateTime($date) : $date;
        $day = self::FR_DAYS[$dt->format('l')];
        $month = self::FR_MONTHS[$dt->format('F')];

        return sprintf('%s %s %s %s', $day, $dt->format('j'), $month, $dt->format('Y'));
    }
}
