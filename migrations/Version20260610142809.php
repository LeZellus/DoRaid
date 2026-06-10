<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260610142809 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE guild_membership (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(10) NOT NULL, requested_at DATETIME NOT NULL, guild_id INT NOT NULL, character_id INT NOT NULL, INDEX IDX_E7D8D2A5F2131EF (guild_id), UNIQUE INDEX UNIQ_E7D8D2A1136BE75 (character_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE guild_membership ADD CONSTRAINT FK_E7D8D2A5F2131EF FOREIGN KEY (guild_id) REFERENCES guild (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE guild_membership ADD CONSTRAINT FK_E7D8D2A1136BE75 FOREIGN KEY (character_id) REFERENCES game_character (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE game_character DROP FOREIGN KEY `FK_41DC71365F2131EF`');
        $this->addSql('DROP INDEX IDX_41DC71365F2131EF ON game_character');
        $this->addSql('ALTER TABLE game_character DROP guild_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE guild_membership DROP FOREIGN KEY FK_E7D8D2A5F2131EF');
        $this->addSql('ALTER TABLE guild_membership DROP FOREIGN KEY FK_E7D8D2A1136BE75');
        $this->addSql('DROP TABLE guild_membership');
        $this->addSql('ALTER TABLE game_character ADD guild_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE game_character ADD CONSTRAINT `FK_41DC71365F2131EF` FOREIGN KEY (guild_id) REFERENCES guild (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_41DC71365F2131EF ON game_character (guild_id)');
    }
}
