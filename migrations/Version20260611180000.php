<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Patch guilds with empty slug using their ID as fallback';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE guild SET slug = CONCAT('guild-', id) WHERE slug = '' OR slug IS NULL");
    }

    public function down(Schema $schema): void
    {
    }
}
