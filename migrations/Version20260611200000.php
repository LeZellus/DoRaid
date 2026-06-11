<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add discord_webhook_url to guild table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE guild ADD discord_webhook_url VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE guild DROP COLUMN discord_webhook_url');
    }
}
