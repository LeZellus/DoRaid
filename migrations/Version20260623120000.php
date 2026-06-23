<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add can_create_raids permission flag to guild_membership';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE guild_membership ADD can_create_raids TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE guild_membership DROP can_create_raids');
    }
}
