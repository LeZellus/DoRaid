<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260610165514 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE enigme_template (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(100) NOT NULL, order_number INT NOT NULL, raid_template_id INT NOT NULL, INDEX IDX_C7A72D5183CBF8AC (raid_template_id), UNIQUE INDEX UNIQ_C7A72D5183CBF8AC551F0F81 (raid_template_id, order_number), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE enigme_template ADD CONSTRAINT FK_C7A72D5183CBF8AC FOREIGN KEY (raid_template_id) REFERENCES raid_template (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE enigme_template DROP FOREIGN KEY FK_C7A72D5183CBF8AC');
        $this->addSql('DROP TABLE enigme_template');
    }
}
