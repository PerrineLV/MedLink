<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * ML-158 : bascule des colonnes datetime en TIMESTAMP WITH TIME ZONE.
 *
 * Jusqu'ici les colonnes étaient en TIMESTAMP WITHOUT TIME ZONE : la base ne
 * stockait pas un instant, mais un cadran, dont le fuseau était implicite.
 * Et il n'était pas le même partout, ce qui est l'origine du bug corrigé ici :
 *
 *   - `appointment.scheduled_at` recevait une valeur envoyée par le client,
 *     sérialisée en UTC par `toISOString()` : le cadran stocké était donc UTC ;
 *   - toutes les autres colonnes sont alimentées côté serveur par
 *     `new \DateTimeImmutable()`, sous un `date.timezone=Europe/Paris` :
 *     le cadran stocké était celui de Paris.
 *
 * À la relecture, PHP appliquait Europe/Paris à tout, ce qui décalait les seuls
 * rendez-vous — de 2 h en heure d'été, de 1 h en heure d'hiver. Les timestamps
 * serveur, eux, faisaient un aller-retour correct par coïncidence, écriture et
 * lecture utilisant le même fuseau implicite.
 *
 * Cette migration interprète donc chaque colonne avec le fuseau sous lequel
 * elle a réellement été écrite. Se tromper de fuseau ici décalerait
 * silencieusement des horodatages de dossier médical : la distinction
 * ci-dessous n'est pas cosmétique.
 *
 * Non touchées volontairement : `doctrine_migration_versions.executed_at` et
 * `refresh_tokens.valid`, qui relèvent de schémas de bundles tiers.
 */
final class Version20260913214500 extends AbstractMigration
{
    /**
     * Colonnes alimentées côté serveur : le cadran stocké est celui de Paris.
     *
     * @var list<string>
     */
    private const PARIS_COLUMNS = [
        'appointment.created_at',
        'failed_login_attempt.created_at',
        'journal_entry.created_at',
        'journal_entry_comment.created_at',
        'message.created_at',
        'password_reset_token.created_at',
        'password_reset_token.expires_at',
        'password_reset_token.used_at',
        'patient_aidant.created_at',
        'patient_aidant.revoked_at',
        'patient_soignant.created_at',
        'patient_soignant.revoked_at',
        'treatment.created_at',
        'treatment_intake.taken_at',
        '"user".consent_at',
        '"user".created_at',
        '"user".deleted_at',
    ];

    /**
     * Colonnes alimentées par le client en UTC.
     *
     * @var list<string>
     */
    private const UTC_COLUMNS = [
        'appointment.scheduled_at',
    ];

    public function getDescription(): string
    {
        return 'ML-158 : colonnes datetime en TIMESTAMP WITH TIME ZONE, avec réinterprétation du fuseau d\'origine';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform,
            'Migration écrite pour PostgreSQL uniquement.'
        );

        foreach (self::PARIS_COLUMNS as $column) {
            $this->alterColumn($column, 'Europe/Paris');
        }

        foreach (self::UTC_COLUMNS as $column) {
            $this->alterColumn($column, 'UTC');
        }
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform,
            'Migration écrite pour PostgreSQL uniquement.'
        );

        // Retour au cadran d'origine, colonne par colonne, pour que le down
        // soit réellement l'inverse du up et non une simple perte du fuseau.
        foreach (self::PARIS_COLUMNS as $column) {
            $this->revertColumn($column, 'Europe/Paris');
        }

        foreach (self::UTC_COLUMNS as $column) {
            $this->revertColumn($column, 'UTC');
        }
    }

    /**
     * `AT TIME ZONE <tz>` appliqué à un timestamp sans fuseau l'interprète
     * comme exprimé dans ce fuseau et produit l'instant correspondant.
     */
    private function alterColumn(string $qualifiedColumn, string $sourceTimezone): void
    {
        [$table, $column] = $this->split($qualifiedColumn);

        $this->addSql(sprintf(
            'ALTER TABLE %s ALTER COLUMN %s TYPE TIMESTAMP(0) WITH TIME ZONE USING %s AT TIME ZONE %s',
            $table,
            $column,
            $column,
            $this->connection->quote($sourceTimezone)
        ));
    }

    /**
     * `AT TIME ZONE <tz>` appliqué à un timestamptz produit le cadran local
     * correspondant dans ce fuseau : l'opération inverse de alterColumn().
     */
    private function revertColumn(string $qualifiedColumn, string $sourceTimezone): void
    {
        [$table, $column] = $this->split($qualifiedColumn);

        $this->addSql(sprintf(
            'ALTER TABLE %s ALTER COLUMN %s TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING %s AT TIME ZONE %s',
            $table,
            $column,
            $column,
            $this->connection->quote($sourceTimezone)
        ));
    }

    /**
     * @return array{string, string}
     */
    private function split(string $qualifiedColumn): array
    {
        $position = strrpos($qualifiedColumn, '.');

        return [
            substr($qualifiedColumn, 0, (int) $position),
            substr($qualifiedColumn, (int) $position + 1),
        ];
    }
}
