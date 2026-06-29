<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260629171000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make user.created_at nullable — existing rows had a wrong default date';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `user` MODIFY created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        $this->addSql("UPDATE `user` SET created_at = NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `user` MODIFY created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }
}
