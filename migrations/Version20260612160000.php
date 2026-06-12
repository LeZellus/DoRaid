<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add raid_comment table with self-referential parent for threaded comments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE raid_comment (
            id         INT AUTO_INCREMENT NOT NULL,
            raid_id    INT NOT NULL,
            author_id  INT NOT NULL,
            parent_id  INT DEFAULT NULL,
            content    LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            INDEX IDX_RC_RAID   (raid_id),
            INDEX IDX_RC_AUTHOR (author_id),
            INDEX IDX_RC_PARENT (parent_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql('ALTER TABLE raid_comment
            ADD CONSTRAINT FK_RC_RAID   FOREIGN KEY (raid_id)   REFERENCES raid (id)         ON DELETE CASCADE,
            ADD CONSTRAINT FK_RC_AUTHOR FOREIGN KEY (author_id) REFERENCES `user` (id),
            ADD CONSTRAINT FK_RC_PARENT FOREIGN KEY (parent_id) REFERENCES raid_comment (id) ON DELETE CASCADE
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE raid_comment DROP FOREIGN KEY FK_RC_RAID');
        $this->addSql('ALTER TABLE raid_comment DROP FOREIGN KEY FK_RC_AUTHOR');
        $this->addSql('ALTER TABLE raid_comment DROP FOREIGN KEY FK_RC_PARENT');
        $this->addSql('DROP TABLE raid_comment');
    }
}
