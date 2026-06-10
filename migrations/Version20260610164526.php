<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260610164526 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE enigme (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(50) NOT NULL, order_number INT NOT NULL, resolved TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, raid_id INT NOT NULL, INDEX IDX_29C413779C55ABC9 (raid_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE enigme_comment (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, enigme_id INT NOT NULL, author_id INT NOT NULL, INDEX IDX_5637BC1CCA19FCF7 (enigme_id), INDEX IDX_5637BC1CF675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE enigme_image (id INT AUTO_INCREMENT NOT NULL, file_path VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, enigme_id INT NOT NULL, added_by_id INT NOT NULL, INDEX IDX_B0D13CD6CA19FCF7 (enigme_id), INDEX IDX_B0D13CD655B127A4 (added_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE enigme ADD CONSTRAINT FK_29C413779C55ABC9 FOREIGN KEY (raid_id) REFERENCES raid (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE enigme_comment ADD CONSTRAINT FK_5637BC1CCA19FCF7 FOREIGN KEY (enigme_id) REFERENCES enigme (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE enigme_comment ADD CONSTRAINT FK_5637BC1CF675F31B FOREIGN KEY (author_id) REFERENCES game_character (id)');
        $this->addSql('ALTER TABLE enigme_image ADD CONSTRAINT FK_B0D13CD6CA19FCF7 FOREIGN KEY (enigme_id) REFERENCES enigme (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE enigme_image ADD CONSTRAINT FK_B0D13CD655B127A4 FOREIGN KEY (added_by_id) REFERENCES game_character (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE enigme DROP FOREIGN KEY FK_29C413779C55ABC9');
        $this->addSql('ALTER TABLE enigme_comment DROP FOREIGN KEY FK_5637BC1CCA19FCF7');
        $this->addSql('ALTER TABLE enigme_comment DROP FOREIGN KEY FK_5637BC1CF675F31B');
        $this->addSql('ALTER TABLE enigme_image DROP FOREIGN KEY FK_B0D13CD6CA19FCF7');
        $this->addSql('ALTER TABLE enigme_image DROP FOREIGN KEY FK_B0D13CD655B127A4');
        $this->addSql('DROP TABLE enigme');
        $this->addSql('DROP TABLE enigme_comment');
        $this->addSql('DROP TABLE enigme_image');
    }
}
