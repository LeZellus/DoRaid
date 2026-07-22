<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260722172255 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Autorise les punitions à durée illimitée (expires_at nullable)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE member_punishment CHANGE expires_at expires_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE member_punishment CHANGE expires_at expires_at DATETIME NOT NULL');
    }
}
