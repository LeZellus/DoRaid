<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260706142725 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE raid_participant ADD initiative_modifiers JSON DEFAULT NULL');
        $this->addSql("UPDATE raid_participant SET initiative_modifiers = '[]' WHERE initiative_modifiers IS NULL");
        $this->addSql('ALTER TABLE raid_participant MODIFY initiative_modifiers JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE raid_participant DROP initiative_modifiers');
    }
}
