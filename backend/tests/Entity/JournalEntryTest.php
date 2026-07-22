<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\JournalEntry;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class JournalEntryTest extends TestCase
{
    public function testConstructorSetsBaseFields(): void
    {
        $patient = new User('patient@medlink.test', 'Jeanne', 'Dupont');
        $author = new User('aidant@medlink.test', 'Paul', 'Martin');

        $entry = new JournalEntry($patient, $author, 3, 4, '12/8', 'Journée calme');

        self::assertSame($patient, $entry->getPatient());
        self::assertSame($author, $entry->getAuthor());
        self::assertSame(3, $entry->getMood());
        self::assertSame(4, $entry->getPainLevel());
        self::assertSame('12/8', $entry->getBloodPressure());
        self::assertSame('Journée calme', $entry->getNote());
        self::assertNull($entry->getId());
        self::assertNull($entry->getPatientId());
        self::assertNull($entry->getAuthorId());
        self::assertCount(0, $entry->getComments());
    }

    public function testConstructorDefaultsNoteToNull(): void
    {
        $patient = new User('patient@medlink.test', 'Jeanne', 'Dupont');

        $entry = new JournalEntry($patient, $patient, 3, 4, '12/8');

        self::assertNull($entry->getNote());
    }

    public function testIsEnteredByCaregiverIsFalseWhenAuthorIsPatient(): void
    {
        $patient = new User('patient@medlink.test', 'Jeanne', 'Dupont');

        $entry = new JournalEntry($patient, $patient, 3, 4, '12/8');

        self::assertFalse($entry->isEnteredByCaregiver());
    }

    public function testIsEnteredByCaregiverIsTrueWhenAuthorDiffersFromPatientById(): void
    {
        $patient = new User('patient@medlink.test', 'Jeanne', 'Dupont');
        $aidant = new User('aidant@medlink.test', 'Paul', 'Martin');
        self::setEntityId($patient, 1);
        self::setEntityId($aidant, 2);

        $entry = new JournalEntry($patient, $aidant, 3, 4, '12/8');

        self::assertTrue($entry->isEnteredByCaregiver());
    }

    public function testGetCreatedAtIsSetAtConstruction(): void
    {
        $patient = new User('patient@medlink.test', 'Jeanne', 'Dupont');
        $before = new \DateTimeImmutable();

        $entry = new JournalEntry($patient, $patient, 3, 4, '12/8');

        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $entry->getCreatedAt());
        self::assertLessThanOrEqual($after, $entry->getCreatedAt());
    }

    private static function setEntityId(User $user, int $id): void
    {
        $reflection = new \ReflectionProperty($user, 'id');
        $reflection->setValue($user, $id);
    }
}
