<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260610140457 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE game_character (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, user_id INT NOT NULL, game_class_id INT NOT NULL, server_id INT NOT NULL, INDEX IDX_41DC7136A76ED395 (user_id), INDEX IDX_41DC71364144F436 (game_class_id), INDEX IDX_41DC71361844E6B7 (server_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE game_character ADD CONSTRAINT FK_41DC7136A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE game_character ADD CONSTRAINT FK_41DC71364144F436 FOREIGN KEY (game_class_id) REFERENCES game_class (id)');
        $this->addSql('ALTER TABLE game_character ADD CONSTRAINT FK_41DC71361844E6B7 FOREIGN KEY (server_id) REFERENCES server (id)');
        $this->addSql('ALTER TABLE `character` DROP FOREIGN KEY `FK_937AB0341844E6B7`');
        $this->addSql('ALTER TABLE `character` DROP FOREIGN KEY `FK_937AB0344144F436`');
        $this->addSql('ALTER TABLE `character` DROP FOREIGN KEY `FK_937AB034A76ED395`');
        $this->addSql('DROP TABLE `character`');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE `character` (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, user_id INT NOT NULL, game_class_id INT NOT NULL, server_id INT NOT NULL, INDEX IDX_937AB0341844E6B7 (server_id), INDEX IDX_937AB0344144F436 (game_class_id), INDEX IDX_937AB034A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE `character` ADD CONSTRAINT `FK_937AB0341844E6B7` FOREIGN KEY (server_id) REFERENCES server (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE `character` ADD CONSTRAINT `FK_937AB0344144F436` FOREIGN KEY (game_class_id) REFERENCES game_class (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE `character` ADD CONSTRAINT `FK_937AB034A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE game_character DROP FOREIGN KEY FK_41DC7136A76ED395');
        $this->addSql('ALTER TABLE game_character DROP FOREIGN KEY FK_41DC71364144F436');
        $this->addSql('ALTER TABLE game_character DROP FOREIGN KEY FK_41DC71361844E6B7');
        $this->addSql('DROP TABLE game_character');
    }
}
