<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260610152813 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE raid_template (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, min_participants INT NOT NULL, max_participants INT NOT NULL, UNIQUE INDEX UNIQ_CA17EDD05E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE raid ADD raid_template_id INT NOT NULL, DROP title, DROP max_participants');
        $this->addSql('ALTER TABLE raid ADD CONSTRAINT FK_578763B383CBF8AC FOREIGN KEY (raid_template_id) REFERENCES raid_template (id)');
        $this->addSql('CREATE INDEX IDX_578763B383CBF8AC ON raid (raid_template_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE raid_template');
        $this->addSql('ALTER TABLE raid DROP FOREIGN KEY FK_578763B383CBF8AC');
        $this->addSql('DROP INDEX IDX_578763B383CBF8AC ON raid');
        $this->addSql('ALTER TABLE raid ADD title VARCHAR(100) NOT NULL, ADD max_participants INT DEFAULT NULL, DROP raid_template_id');
    }
}
