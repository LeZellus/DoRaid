<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260629080644 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE member_note (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, membership_id INT NOT NULL, author_id INT NOT NULL, INDEX IDX_3335D6481FB354CD (membership_id), INDEX IDX_3335D648F675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE member_note ADD CONSTRAINT FK_3335D6481FB354CD FOREIGN KEY (membership_id) REFERENCES guild_membership (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE member_note ADD CONSTRAINT FK_3335D648F675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE guild_membership DROP note');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE member_note DROP FOREIGN KEY FK_3335D6481FB354CD');
        $this->addSql('ALTER TABLE member_note DROP FOREIGN KEY FK_3335D648F675F31B');
        $this->addSql('DROP TABLE member_note');
        $this->addSql('ALTER TABLE guild_membership ADD note LONGTEXT DEFAULT NULL');
    }
}
