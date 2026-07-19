<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260719150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Images de salle (back-office uniquement)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE salle_image (id INT AUTO_INCREMENT NOT NULL, file_path VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, salle_id INT NOT NULL, INDEX IDX_28380736DC304035 (salle_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE salle_image ADD CONSTRAINT FK_28380736DC304035 FOREIGN KEY (salle_id) REFERENCES salle (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE salle_image DROP FOREIGN KEY FK_28380736DC304035');
        $this->addSql('DROP TABLE salle_image');
    }
}
