<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260610151327 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE raid (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, status VARCHAR(10) NOT NULL, max_participants INT DEFAULT NULL, scheduled_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, guild_id INT NOT NULL, creator_id INT NOT NULL, INDEX IDX_578763B35F2131EF (guild_id), INDEX IDX_578763B361220EA6 (creator_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE raid_participant (id INT AUTO_INCREMENT NOT NULL, joined_at DATETIME NOT NULL, raid_id INT NOT NULL, character_id INT NOT NULL, INDEX IDX_518E9C059C55ABC9 (raid_id), INDEX IDX_518E9C051136BE75 (character_id), UNIQUE INDEX UNIQ_518E9C059C55ABC91136BE75 (raid_id, character_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE raid ADD CONSTRAINT FK_578763B35F2131EF FOREIGN KEY (guild_id) REFERENCES guild (id)');
        $this->addSql('ALTER TABLE raid ADD CONSTRAINT FK_578763B361220EA6 FOREIGN KEY (creator_id) REFERENCES game_character (id)');
        $this->addSql('ALTER TABLE raid_participant ADD CONSTRAINT FK_518E9C059C55ABC9 FOREIGN KEY (raid_id) REFERENCES raid (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE raid_participant ADD CONSTRAINT FK_518E9C051136BE75 FOREIGN KEY (character_id) REFERENCES game_character (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE raid DROP FOREIGN KEY FK_578763B35F2131EF');
        $this->addSql('ALTER TABLE raid DROP FOREIGN KEY FK_578763B361220EA6');
        $this->addSql('ALTER TABLE raid_participant DROP FOREIGN KEY FK_518E9C059C55ABC9');
        $this->addSql('ALTER TABLE raid_participant DROP FOREIGN KEY FK_518E9C051136BE75');
        $this->addSql('DROP TABLE raid');
        $this->addSql('DROP TABLE raid_participant');
    }
}
