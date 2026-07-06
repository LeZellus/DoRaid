<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260706122620 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE raid_group (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(50) DEFAULT NULL, position INT NOT NULL, raid_id INT NOT NULL, INDEX IDX_BC4972A89C55ABC9 (raid_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE raid_group ADD CONSTRAINT FK_BC4972A89C55ABC9 FOREIGN KEY (raid_id) REFERENCES raid (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE raid_participant ADD group_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE raid_participant ADD CONSTRAINT FK_518E9C05FE54D947 FOREIGN KEY (group_id) REFERENCES raid_group (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_518E9C05FE54D947 ON raid_participant (group_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE raid_participant DROP FOREIGN KEY FK_518E9C05FE54D947');
        $this->addSql('DROP INDEX IDX_518E9C05FE54D947 ON raid_participant');
        $this->addSql('ALTER TABLE raid_participant DROP group_id');
        $this->addSql('ALTER TABLE raid_group DROP FOREIGN KEY FK_BC4972A89C55ABC9');
        $this->addSql('DROP TABLE raid_group');
    }
}
