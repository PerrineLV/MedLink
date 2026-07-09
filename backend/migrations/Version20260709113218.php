<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260709113218 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ML-47 : ajoute revoked_at sur patient_aidant/patient_soignant pour distinguer une invitation en attente d\'un lien révoqué.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE patient_aidant ADD revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE patient_soignant ADD revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE patient_aidant DROP revoked_at');
        $this->addSql('ALTER TABLE patient_soignant DROP revoked_at');
    }
}
