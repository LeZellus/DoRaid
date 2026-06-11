<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add duration (minutes) to raid_template';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE raid_template ADD duration INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE raid_template DROP COLUMN duration');
    }
}
