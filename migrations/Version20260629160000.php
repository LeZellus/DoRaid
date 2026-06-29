<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260629160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add feedback table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE feedback (
                id          INT AUTO_INCREMENT NOT NULL,
                user_id     INT DEFAULT NULL,
                type        VARCHAR(20) NOT NULL,
                status      VARCHAR(20) NOT NULL DEFAULT 'open',
                title       VARCHAR(200) NOT NULL,
                description LONGTEXT DEFAULT NULL,
                page        VARCHAR(500) DEFAULT NULL,
                created_at  DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX IDX_D2294458A76ED395 (user_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ");
        $this->addSql('ALTER TABLE feedback ADD CONSTRAINT FK_D2294458A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE feedback DROP FOREIGN KEY FK_D2294458A76ED395');
        $this->addSql('DROP TABLE feedback');
    }
}
