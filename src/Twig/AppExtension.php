<?php

namespace App\Twig;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    private const FR_DAYS = ['Sunday' => 'Dimanche', 'Monday' => 'Lundi', 'Tuesday' => 'Mardi',
        'Wednesday' => 'Mercredi', 'Thursday' => 'Jeudi', 'Friday' => 'Vendredi', 'Saturday' => 'Samedi'];

    private const FR_MONTHS = ['January' => 'janvier', 'February' => 'février', 'March' => 'mars',
        'April' => 'avril', 'May' => 'mai', 'June' => 'juin', 'July' => 'juillet',
        'August' => 'août', 'September' => 'septembre', 'October' => 'octobre',
        'November' => 'novembre', 'December' => 'décembre'];

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('date_fr', $this->dateFr(...)),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('raid_template_og_image', $this->raidTemplateOgImage(...)),
        ];
    }

    /**
     * Retourne le chemin de la bannière OG dédiée à ce type de raid, si elle existe sur le disque.
     */
    public function raidTemplateOgImage(string $raidTemplateName): ?string
    {
        $slug = (new AsciiSlugger())->slug($raidTemplateName)->lower()->toString();
        $relativePath = 'og/raid-' . $slug . '.png';

        return is_file($this->projectDir . '/public/' . $relativePath) ? $relativePath : null;
    }

    public function dateFr(\DateTimeInterface|string $date): string
    {
        $dt = is_string($date) ? new \DateTime($date) : $date;
        $day = self::FR_DAYS[$dt->format('l')];
        $month = self::FR_MONTHS[$dt->format('F')];

        return sprintf('%s %s %s %s', $day, $dt->format('j'), $month, $dt->format('Y'));
    }
}
