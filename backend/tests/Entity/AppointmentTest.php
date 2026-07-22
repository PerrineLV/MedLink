<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Appointment;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class AppointmentTest extends TestCase
{
    private function createUser(): User
    {
        return new User('user@medlink.test', 'Jeanne', 'Dupont');
    }

    public function testConstructorSetsBaseFieldsAndDefaultsToPlanned(): void
    {
        $patient = $this->createUser();
        $soignant = $this->createUser();
        $scheduledAt = new \DateTimeImmutable('+1 day');

        $appointment = new Appointment($patient, $soignant, $scheduledAt, 'Bilan trimestriel');

        self::assertSame($patient, $appointment->getPatient());
        self::assertSame($soignant, $appointment->getSoignant());
        self::assertSame($scheduledAt, $appointment->getScheduledAt());
        self::assertSame('Bilan trimestriel', $appointment->getNotes());
        self::assertSame(Appointment::STATUS_PLANNED, $appointment->getStatus());
        self::assertNull($appointment->getId());
        self::assertNull($appointment->getPatientId());
        self::assertNull($appointment->getSoignantId());
    }

    public function testConstructorDefaultsNotesToNull(): void
    {
        $appointment = new Appointment($this->createUser(), $this->createUser(), new \DateTimeImmutable());

        self::assertNull($appointment->getNotes());
    }

    public function testSetScheduledAtUpdatesValue(): void
    {
        $appointment = new Appointment($this->createUser(), $this->createUser(), new \DateTimeImmutable());
        $newDate = new \DateTimeImmutable('+2 days');

        $appointment->setScheduledAt($newDate);

        self::assertSame($newDate, $appointment->getScheduledAt());
    }

    public function testSetStatusUpdatesValue(): void
    {
        $appointment = new Appointment($this->createUser(), $this->createUser(), new \DateTimeImmutable());

        $appointment->setStatus(Appointment::STATUS_CANCELLED);

        self::assertSame(Appointment::STATUS_CANCELLED, $appointment->getStatus());
    }

    public function testSetNotesUpdatesValue(): void
    {
        $appointment = new Appointment($this->createUser(), $this->createUser(), new \DateTimeImmutable());

        $appointment->setNotes('Nouvelle note');

        self::assertSame('Nouvelle note', $appointment->getNotes());
    }

    public function testGetCreatedAtIsSetAtConstruction(): void
    {
        $before = new \DateTimeImmutable();

        $appointment = new Appointment($this->createUser(), $this->createUser(), new \DateTimeImmutable());

        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $appointment->getCreatedAt());
        self::assertLessThanOrEqual($after, $appointment->getCreatedAt());
    }
}
