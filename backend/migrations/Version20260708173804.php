<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260708173804 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs

        // Le passage d'un horaire précis à un moment de la journée (matin/
        // midi/soir/personnalisé) rend les horaires existants incompatibles
        // avec le nouveau schéma. Ces tables ne contiennent que des données
        // de fixtures de dev (voir AppFixtures), donc on les vide plutôt que
        // de tenter une migration de données : `doctrine:fixtures:load` les
        // regénère dans le nouveau format.
        $this->addSql('TRUNCATE TABLE treatment_schedule CASCADE');

        $this->addSql('ALTER TABLE treatment_schedule ADD moment VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE treatment_schedule ADD custom_label VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE treatment_schedule ADD position INT NOT NULL');
        $this->addSql('ALTER TABLE treatment_schedule DROP scheduled_time');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('TRUNCATE TABLE treatment_schedule CASCADE');

        $this->addSql('ALTER TABLE treatment_schedule ADD scheduled_time VARCHAR(5) NOT NULL');
        $this->addSql('ALTER TABLE treatment_schedule DROP moment');
        $this->addSql('ALTER TABLE treatment_schedule DROP custom_label');
        $this->addSql('ALTER TABLE treatment_schedule DROP position');
    }
}
