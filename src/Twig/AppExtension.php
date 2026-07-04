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
            new TwigFunction('raid_guide_og_image', $this->raidGuideOgImage(...)),
            new TwigFunction('has_raid_guide', $this->hasRaidGuide(...)),
            new TwigFunction('og_image_version', $this->ogImageVersion(...)),
        ];
    }

    /**
     * Indique si un guide statique existe pour ce type de raid.
     */
    public function hasRaidGuide(string $raidTemplateName): bool
    {
        $slug = (new AsciiSlugger())->slug($raidTemplateName)->lower()->toString();

        return is_file($this->projectDir . '/config/raid_guides/' . $slug . '.json');
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

    /**
     * Retourne le chemin de la bannière OG dédiée au guide de ce type de raid, si elle existe sur le disque.
     */
    public function raidGuideOgImage(string $raidTemplateName): ?string
    {
        $slug = (new AsciiSlugger())->slug($raidTemplateName)->lower()->toString();
        $relativePath = 'og/guide-' . $slug . '.png';

        return is_file($this->projectDir . '/public/' . $relativePath) ? $relativePath : null;
    }

    /**
     * Suffixe de version (basé sur la date de modification du fichier) à ajouter à une
     * URL d'image OG statique, pour forcer Discord/Twitter/Facebook à retélécharger
     * l'image après une régénération au lieu de servir leur copie mise en cache.
     */
    public function ogImageVersion(string $relativePath): string
    {
        $absolutePath = $this->projectDir . '/public/' . $relativePath;

        return is_file($absolutePath) ? '?v=' . filemtime($absolutePath) : '';
    }

    public function dateFr(\DateTimeInterface|string $date): string
    {
        $dt = is_string($date) ? new \DateTime($date) : $date;
        $day = self::FR_DAYS[$dt->format('l')];
        $month = self::FR_MONTHS[$dt->format('F')];

        return sprintf('%s %s %s %s', $day, $dt->format('j'), $month, $dt->format('Y'));
    }
}
