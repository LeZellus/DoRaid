<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add source_template_id to enigme to track origin and enable title sync';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enigme ADD source_template_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE enigme ADD CONSTRAINT FK_29C4137739A55F18 FOREIGN KEY (source_template_id) REFERENCES enigme_template (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_29C4137739A55F18 ON enigme (source_template_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enigme DROP FOREIGN KEY FK_29C4137739A55F18');
        $this->addSql('DROP INDEX IDX_29C4137739A55F18 ON enigme');
        $this->addSql('ALTER TABLE enigme DROP COLUMN source_template_id');
    }
}
