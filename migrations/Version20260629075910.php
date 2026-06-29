<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260629075910 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE game_character CHANGE optimization_level optimization_level VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE guild_membership ADD note LONGTEXT DEFAULT NULL');
        $this->addSql('DROP INDEX IDX_NOTIF_READ ON notification');
        $this->addSql('ALTER TABLE notification CHANGE read_at read_at DATETIME DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE notification RENAME INDEX idx_notif_user TO IDX_BF5476CAA76ED395');
        $this->addSql('ALTER TABLE raid CHANGE is_public is_public TINYINT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE raid_comment CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE raid_comment RENAME INDEX idx_rc_raid TO IDX_8B9D03969C55ABC9');
        $this->addSql('ALTER TABLE raid_comment RENAME INDEX idx_rc_author TO IDX_8B9D0396F675F31B');
        $this->addSql('ALTER TABLE raid_comment RENAME INDEX idx_rc_parent TO IDX_8B9D0396727ACA70');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE game_character CHANGE optimization_level optimization_level VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE guild_membership DROP note');
        $this->addSql('ALTER TABLE notification CHANGE read_at read_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX IDX_NOTIF_READ ON notification (user_id, read_at)');
        $this->addSql('ALTER TABLE notification RENAME INDEX idx_bf5476caa76ed395 TO IDX_NOTIF_USER');
        $this->addSql('ALTER TABLE raid CHANGE is_public is_public TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE raid_comment CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE raid_comment RENAME INDEX idx_8b9d03969c55abc9 TO IDX_RC_RAID');
        $this->addSql('ALTER TABLE raid_comment RENAME INDEX idx_8b9d0396f675f31b TO IDX_RC_AUTHOR');
        $this->addSql('ALTER TABLE raid_comment RENAME INDEX idx_8b9d0396727aca70 TO IDX_RC_PARENT');
    }
}
