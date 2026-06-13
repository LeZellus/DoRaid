<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260613180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notification table for in-app notifications';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE notification (
            id         INT AUTO_INCREMENT NOT NULL,
            user_id    INT NOT NULL,
            type       VARCHAR(50)  NOT NULL,
            title      VARCHAR(255) NOT NULL,
            message    VARCHAR(255) NOT NULL,
            link       VARCHAR(500) DEFAULT NULL,
            read_at    DATETIME     DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            created_at DATETIME     NOT NULL     COMMENT '(DC2Type:datetime_immutable)',
            INDEX IDX_NOTIF_USER (user_id),
            INDEX IDX_NOTIF_READ (user_id, read_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql('ALTER TABLE notification
            ADD CONSTRAINT FK_NOTIF_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_NOTIF_USER');
        $this->addSql('DROP TABLE notification');
    }
}
