<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611180001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove enigme.title: link all enigmes to their EnigmeTemplate and drop the copied title column';
    }

    public function up(Schema $schema): void
    {
        // Link legacy enigmes (source_template_id IS NULL) to their template via raidTemplate + orderNumber
        $this->addSql(
            'UPDATE enigme e
             JOIN raid r ON e.raid_id = r.id
             JOIN enigme_template et ON et.raid_template_id = r.raid_template_id
                                    AND et.order_number = e.order_number
             SET e.source_template_id = et.id
             WHERE e.source_template_id IS NULL'
        );

        // Drop old FK (SET NULL), re-add as NOT NULL with RESTRICT
        $this->addSql('ALTER TABLE enigme DROP FOREIGN KEY FK_29C4137739A55F18');
        $this->addSql('ALTER TABLE enigme MODIFY source_template_id INT NOT NULL');
        $this->addSql('ALTER TABLE enigme ADD CONSTRAINT FK_29C4137739A55F18 FOREIGN KEY (source_template_id) REFERENCES enigme_template (id)');

        // Drop the copied title column
        $this->addSql('ALTER TABLE enigme DROP COLUMN title');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enigme ADD title VARCHAR(50) NOT NULL DEFAULT \'\'');
        $this->addSql('UPDATE enigme e JOIN enigme_template et ON e.source_template_id = et.id SET e.title = et.title');
        $this->addSql('ALTER TABLE enigme DROP FOREIGN KEY FK_29C4137739A55F18');
        $this->addSql('ALTER TABLE enigme MODIFY source_template_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE enigme ADD CONSTRAINT FK_29C4137739A55F18 FOREIGN KEY (source_template_id) REFERENCES enigme_template (id) ON DELETE SET NULL');
    }
}
