<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix empty strings stored in optimization_level instead of NULL';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE game_character SET optimization_level = NULL WHERE optimization_level = ''");
    }

    public function down(Schema $schema): void
    {
    }
}
