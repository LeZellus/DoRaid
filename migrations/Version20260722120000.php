<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260722120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table member_punishment (punitions temporaires sur un membre de guilde)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE member_punishment (id INT AUTO_INCREMENT NOT NULL, reason LONGTEXT NOT NULL, created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, membership_id INT NOT NULL, author_id INT NOT NULL, INDEX IDX_904458D81FB354CD (membership_id), INDEX IDX_904458D8F675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE member_punishment ADD CONSTRAINT FK_904458D81FB354CD FOREIGN KEY (membership_id) REFERENCES guild_membership (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE member_punishment ADD CONSTRAINT FK_904458D8F675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE member_punishment DROP FOREIGN KEY FK_904458D81FB354CD');
        $this->addSql('ALTER TABLE member_punishment DROP FOREIGN KEY FK_904458D8F675F31B');
        $this->addSql('DROP TABLE member_punishment');
    }
}
