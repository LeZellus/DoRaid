<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set all existing raids as public (retroactive default)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE raid SET is_public = 1 WHERE is_public = 0');
    }

    public function down(Schema $schema): void
    {
    }
}
