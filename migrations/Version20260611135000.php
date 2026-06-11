<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611135000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE guild ADD slug VARCHAR(160) DEFAULT NULL');
        $this->addSql("UPDATE guild SET slug = LOWER(REGEXP_REPLACE(REGEXP_REPLACE(name, '[^a-zA-Z0-9]+', '-'), '^-|-$', ''))");
        $this->addSql('ALTER TABLE guild MODIFY slug VARCHAR(160) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_75407DAB989D9B62 ON guild (slug)');
        $this->addSql('ALTER TABLE raid_participant CHANGE status status VARCHAR(10) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_75407DAB989D9B62 ON guild');
        $this->addSql('ALTER TABLE guild DROP slug');
        $this->addSql('ALTER TABLE raid_participant CHANGE status status VARCHAR(10) DEFAULT \'accepted\' NOT NULL');
    }
}
