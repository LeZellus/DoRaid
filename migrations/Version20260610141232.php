<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260610141232 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE guild (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, server_id INT NOT NULL, owner_id INT NOT NULL, INDEX IDX_75407DAB1844E6B7 (server_id), INDEX IDX_75407DAB7E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE guild ADD CONSTRAINT FK_75407DAB1844E6B7 FOREIGN KEY (server_id) REFERENCES server (id)');
        $this->addSql('ALTER TABLE guild ADD CONSTRAINT FK_75407DAB7E3C61F9 FOREIGN KEY (owner_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE game_character ADD guild_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE game_character ADD CONSTRAINT FK_41DC71365F2131EF FOREIGN KEY (guild_id) REFERENCES guild (id)');
        $this->addSql('CREATE INDEX IDX_41DC71365F2131EF ON game_character (guild_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE guild DROP FOREIGN KEY FK_75407DAB1844E6B7');
        $this->addSql('ALTER TABLE guild DROP FOREIGN KEY FK_75407DAB7E3C61F9');
        $this->addSql('DROP TABLE guild');
        $this->addSql('ALTER TABLE game_character DROP FOREIGN KEY FK_41DC71365F2131EF');
        $this->addSql('DROP INDEX IDX_41DC71365F2131EF ON game_character');
        $this->addSql('ALTER TABLE game_character DROP guild_id');
    }
}
