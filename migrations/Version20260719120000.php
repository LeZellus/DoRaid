<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260719120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Répartiteur de raid : gemmes, mobs (taux de drop) et salles (compositions de mobs)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE gem (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, value INT NOT NULL, UNIQUE INDEX UNIQ_995086B05E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE mob (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, raid_template_id INT NOT NULL, INDEX IDX_FE97F67D83CBF8AC (raid_template_id), UNIQUE INDEX UNIQ_FE97F67D83CBF8AC5E237E06 (raid_template_id, name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE mob_drop_rate (id INT AUTO_INCREMENT NOT NULL, probability DOUBLE PRECISION NOT NULL, mob_id INT NOT NULL, gem_id INT NOT NULL, INDEX IDX_8107325016E57E11 (mob_id), INDEX IDX_81073250A5AD5580 (gem_id), UNIQUE INDEX UNIQ_8107325016E57E11A5AD5580 (mob_id, gem_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE salle (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, level_min INT NOT NULL, level_max INT NOT NULL, order_number INT NOT NULL, raid_template_id INT NOT NULL, INDEX IDX_4E977E5C83CBF8AC (raid_template_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE salle_composition (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(50) DEFAULT NULL, order_number INT NOT NULL, salle_id INT NOT NULL, INDEX IDX_F2C0D4C4DC304035 (salle_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE salle_composition_mob (id INT AUTO_INCREMENT NOT NULL, quantity INT NOT NULL, composition_id INT NOT NULL, mob_id INT NOT NULL, INDEX IDX_581A4F4487A2E12 (composition_id), INDEX IDX_581A4F4416E57E11 (mob_id), UNIQUE INDEX UNIQ_581A4F4487A2E1216E57E11 (composition_id, mob_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE mob ADD CONSTRAINT FK_FE97F67D83CBF8AC FOREIGN KEY (raid_template_id) REFERENCES raid_template (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mob_drop_rate ADD CONSTRAINT FK_8107325016E57E11 FOREIGN KEY (mob_id) REFERENCES mob (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mob_drop_rate ADD CONSTRAINT FK_81073250A5AD5580 FOREIGN KEY (gem_id) REFERENCES gem (id)');
        $this->addSql('ALTER TABLE salle ADD CONSTRAINT FK_4E977E5C83CBF8AC FOREIGN KEY (raid_template_id) REFERENCES raid_template (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE salle_composition ADD CONSTRAINT FK_F2C0D4C4DC304035 FOREIGN KEY (salle_id) REFERENCES salle (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE salle_composition_mob ADD CONSTRAINT FK_581A4F4487A2E12 FOREIGN KEY (composition_id) REFERENCES salle_composition (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE salle_composition_mob ADD CONSTRAINT FK_581A4F4416E57E11 FOREIGN KEY (mob_id) REFERENCES mob (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mob DROP FOREIGN KEY FK_FE97F67D83CBF8AC');
        $this->addSql('ALTER TABLE mob_drop_rate DROP FOREIGN KEY FK_8107325016E57E11');
        $this->addSql('ALTER TABLE mob_drop_rate DROP FOREIGN KEY FK_81073250A5AD5580');
        $this->addSql('ALTER TABLE salle DROP FOREIGN KEY FK_4E977E5C83CBF8AC');
        $this->addSql('ALTER TABLE salle_composition DROP FOREIGN KEY FK_F2C0D4C4DC304035');
        $this->addSql('ALTER TABLE salle_composition_mob DROP FOREIGN KEY FK_581A4F4487A2E12');
        $this->addSql('ALTER TABLE salle_composition_mob DROP FOREIGN KEY FK_581A4F4416E57E11');
        $this->addSql('DROP TABLE salle_composition_mob');
        $this->addSql('DROP TABLE mob_drop_rate');
        $this->addSql('DROP TABLE salle_composition');
        $this->addSql('DROP TABLE mob');
        $this->addSql('DROP TABLE salle');
        $this->addSql('DROP TABLE gem');
    }
}
