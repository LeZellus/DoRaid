<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_public column to raid';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE raid ADD is_public TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE raid DROP COLUMN is_public');
    }
}
