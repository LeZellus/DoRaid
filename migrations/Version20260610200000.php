<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260610200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add status column to raid_participant (pending/accepted); existing rows become accepted';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE raid_participant ADD status VARCHAR(10) NOT NULL DEFAULT 'accepted'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE raid_participant DROP COLUMN status');
    }
}
