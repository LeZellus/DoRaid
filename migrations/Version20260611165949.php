<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611165949 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ON DELETE CASCADE to enigme_comment.author_id and enigme_image.added_by_id FKs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enigme_comment DROP FOREIGN KEY `FK_5637BC1CF675F31B`');
        $this->addSql('ALTER TABLE enigme_comment ADD CONSTRAINT FK_5637BC1CF675F31B FOREIGN KEY (author_id) REFERENCES game_character (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE enigme_image DROP FOREIGN KEY `FK_B0D13CD655B127A4`');
        $this->addSql('ALTER TABLE enigme_image ADD CONSTRAINT FK_B0D13CD655B127A4 FOREIGN KEY (added_by_id) REFERENCES game_character (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enigme_comment DROP FOREIGN KEY `FK_5637BC1CF675F31B`');
        $this->addSql('ALTER TABLE enigme_comment ADD CONSTRAINT FK_5637BC1CF675F31B FOREIGN KEY (author_id) REFERENCES game_character (id)');

        $this->addSql('ALTER TABLE enigme_image DROP FOREIGN KEY `FK_B0D13CD655B127A4`');
        $this->addSql('ALTER TABLE enigme_image ADD CONSTRAINT FK_B0D13CD655B127A4 FOREIGN KEY (added_by_id) REFERENCES game_character (id)');
    }
}
