<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add level and optimization_level to game_character';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_character ADD level INT DEFAULT NULL, ADD optimization_level VARCHAR(10) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_character DROP COLUMN level, DROP COLUMN optimization_level');
    }
}
