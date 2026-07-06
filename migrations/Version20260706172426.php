<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Renomme les valeurs stockées dans raid_participant.initiative_modifiers pour refléter
 * les vrais noms des brandades (icônes ajoutées dans public/uploads/divers).
 */
final class Version20260706172426 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renomme les modificateurs d\'initiative stockés (brandade_p100 -> stimulante, etc.)';
    }

    private const MAP = [
        'brandade_p100'   => 'stimulante',
        'brandade_p200'   => 'energisante',
        'brandade_p500'   => 'exaltante',
        'brandade_m100'   => 'fatiguante',
        'brandade_m200'   => 'ereintante',
        'brandade_m500'   => 'epuisante',
        'dofus_cauchemar' => 'cauchemard',
    ];

    public function up(Schema $schema): void
    {
        foreach (self::MAP as $old => $new) {
            $this->addSql(
                "UPDATE raid_participant SET initiative_modifiers = REPLACE(initiative_modifiers, :old, :new) WHERE initiative_modifiers LIKE :like",
                ['old' => '"' . $old . '"', 'new' => '"' . $new . '"', 'like' => '%"' . $old . '"%']
            );
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::MAP as $old => $new) {
            $this->addSql(
                "UPDATE raid_participant SET initiative_modifiers = REPLACE(initiative_modifiers, :new, :old) WHERE initiative_modifiers LIKE :like",
                ['new' => '"' . $new . '"', 'old' => '"' . $old . '"', 'like' => '%"' . $new . '"%']
            );
        }
    }
}
