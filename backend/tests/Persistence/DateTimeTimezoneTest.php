<?php

declare(strict_types=1);

namespace App\Tests\Persistence;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\DateTimeImmutableType;
use Doctrine\DBAL\Types\DateTimeTzImmutableType;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * ML-158 : les colonnes datetime doivent stocker des instants, pas des cadrans.
 *
 * Le bug d'origine : `appointment.scheduled_at` était en TIMESTAMP WITHOUT TIME
 * ZONE. Le client y écrivait un cadran UTC (`toISOString()`), mais Doctrine le
 * relisait en appliquant le `date.timezone` du serveur, alors Europe/Paris.
 * Un rendez-vous planifié à 10 h s'affichait donc à 8 h chez la patiente —
 * décalage de 2 h en heure d'été, de 1 h en heure d'hiver, puisqu'il suit
 * l'offset de Paris et non une constante.
 *
 * Le POST ne montrait rien : l'entité était encore en mémoire, avec son fuseau
 * d'origine. Seule une relecture depuis la base révélait l'écart.
 */
final class DateTimeTimezoneTest extends TestCase
{
    /**
     * Le cœur du correctif : avec un type porteur de fuseau, l'instant survit à
     * l'aller-retour même si le serveur et la donnée ne sont pas dans le même
     * fuseau. C'est ce qui rend la persistance indépendante de `date.timezone`.
     */
    #[DataProvider('serverTimezones')]
    public function testInstantSurvivesRoundTripWhateverTheServerTimezone(string $serverTimezone): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set($serverTimezone);

        try {
            $platform = new PostgreSQLPlatform();
            $type = new DateTimeTzImmutableType();

            // Ce qu'envoie un client : un instant en UTC.
            $sent = new \DateTimeImmutable('2026-12-01T08:00:00+00:00');

            $stored = $type->convertToDatabaseValue($sent, $platform);
            $reread = $type->convertToPHPValue($stored, $platform);

            self::assertInstanceOf(\DateTimeImmutable::class, $reread);
            self::assertSame(
                $sent->getTimestamp(),
                $reread->getTimestamp(),
                sprintf(
                    'Avec date.timezone=%s, l\'instant a été altéré par l\'aller-retour en base : '
                    .'envoyé %s, relu %s. C\'est exactement le défaut de ML-158.',
                    $serverTimezone,
                    $sent->format(\DateTimeInterface::ATOM),
                    $reread->format(\DateTimeInterface::ATOM),
                ),
            );
        } finally {
            date_default_timezone_set($previous);
        }
    }

    /**
     * Le test du test : sans fuseau porté par la colonne, l'aller-retour altère
     * bien l'instant. Sans ce cas, rien ne prouverait que le test précédent
     * vérifie quelque chose — il passerait même si les deux types étaient
     * équivalents.
     */
    public function testTimezoneLessTypeIsPreciselyWhatBrokeAppointments(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('Europe/Paris');

        try {
            $platform = new PostgreSQLPlatform();
            $type = new DateTimeImmutableType();

            $sent = new \DateTimeImmutable('2026-12-01T08:00:00+00:00');
            $reread = $type->convertToPHPValue(
                $type->convertToDatabaseValue($sent, $platform),
                $platform,
            );

            self::assertNotSame(
                $sent->getTimestamp(),
                $reread->getTimestamp(),
                'Le type sans fuseau devrait altérer l\'instant sous un serveur en Europe/Paris. '
                .'S\'il ne le fait plus, ce garde-fou ne prouve plus rien et le test ci-dessus '
                .'non plus : revoir les deux.',
            );
        } finally {
            date_default_timezone_set($previous);
        }
    }

    /**
     * Garde-fou structurel : c'est lui qui protège les entités à venir.
     *
     * Corriger les onze entités existantes ne sert à rien si la douzième
     * réintroduit le défaut. Le test balaie donc le répertoire plutôt qu'une
     * liste figée — une liste codée en dur laisserait passer précisément le
     * fichier que personne n'aurait pensé à y ajouter.
     */
    public function testNoEntityMapsADateTimeColumnWithoutTimezone(): void
    {
        $offenders = [];

        foreach (glob(__DIR__.'/../../src/Entity/*.php') ?: [] as $file) {
            $source = file_get_contents($file);

            if (false !== $source && str_contains($source, 'Types::'.Types::DATETIME_IMMUTABLE)) {
                $offenders[] = basename($file);
            }
        }

        self::assertSame(
            [],
            $offenders,
            sprintf(
                'Ces entités déclarent une colonne en %s, qui ne stocke pas de fuseau : %s. '
                .'Utiliser %s, sans quoi la valeur relue dépend du date.timezone du serveur '
                .'et se décale silencieusement (ML-158).',
                'Types::'.Types::DATETIME_IMMUTABLE,
                implode(', ', $offenders),
                'Types::'.Types::DATETIMETZ_IMMUTABLE,
            ),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function serverTimezones(): iterable
    {
        yield 'serveur en UTC' => ['UTC'];
        yield 'serveur en Europe/Paris (heure d\'hiver)' => ['Europe/Paris'];
        yield 'serveur dans un fuseau sans rapport' => ['America/New_York'];
    }
}
