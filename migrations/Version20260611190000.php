<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique constraint on enigme(raid_id, order_number)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX uniq_enigme_raid_order ON enigme (raid_id, order_number)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_enigme_raid_order ON enigme');
    }
}
