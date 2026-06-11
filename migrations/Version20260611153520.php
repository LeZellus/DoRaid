<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611153520 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Remove duplicate characters keeping the one with the lowest id
        $this->addSql('
            DELETE gc FROM game_character gc
            INNER JOIN game_character gc2
                ON gc.name = gc2.name AND gc.server_id = gc2.server_id AND gc.id > gc2.id
        ');
        $this->addSql('CREATE UNIQUE INDEX uniq_character_name_server ON game_character (name, server_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_character_name_server ON game_character');
    }
}
