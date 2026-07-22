<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Treatment;
use App\Entity\TreatmentSchedule;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class TreatmentTest extends TestCase
{
    private function createUser(): User
    {
        return new User('user@medlink.test', 'Jeanne', 'Dupont');
    }

    public function testConstructorSetsBaseFields(): void
    {
        $patient = $this->createUser();
        $prescriber = $this->createUser();

        $treatment = new Treatment($patient, 'Doliprane', '1000mg', $prescriber);

        self::assertSame($patient, $treatment->getPatient());
        self::assertSame('Doliprane', $treatment->getName());
        self::assertSame('1000mg', $treatment->getDosage());
        self::assertSame($prescriber, $treatment->getPrescribedBy());
        self::assertTrue($treatment->isActive());
        self::assertNull($treatment->getId());
        self::assertNull($treatment->getPatientId());
        self::assertNull($treatment->getPrescribedById());
        self::assertCount(0, $treatment->getSchedules());
    }

    public function testSetNameUpdatesValue(): void
    {
        $treatment = new Treatment($this->createUser(), 'Doliprane', '1000mg', $this->createUser());

        $treatment->setName('Ibuprofène');

        self::assertSame('Ibuprofène', $treatment->getName());
    }

    public function testSetDosageUpdatesValue(): void
    {
        $treatment = new Treatment($this->createUser(), 'Doliprane', '1000mg', $this->createUser());

        $treatment->setDosage('500mg');

        self::assertSame('500mg', $treatment->getDosage());
    }

    public function testAddScheduleAppendsToCollection(): void
    {
        $treatment = new Treatment($this->createUser(), 'Doliprane', '1000mg', $this->createUser());
        $schedule = new TreatmentSchedule($treatment, TreatmentSchedule::MOMENT_MORNING);

        $treatment->addSchedule($schedule);

        self::assertCount(1, $treatment->getSchedules());
        self::assertSame($schedule, $treatment->getSchedules()->first());
    }

    public function testClearSchedulesEmptiesCollection(): void
    {
        $treatment = new Treatment($this->createUser(), 'Doliprane', '1000mg', $this->createUser());
        $treatment->addSchedule(new TreatmentSchedule($treatment, TreatmentSchedule::MOMENT_MORNING));

        $treatment->clearSchedules();

        self::assertCount(0, $treatment->getSchedules());
    }

    public function testSetActiveUpdatesValue(): void
    {
        $treatment = new Treatment($this->createUser(), 'Doliprane', '1000mg', $this->createUser());

        $treatment->setActive(false);

        self::assertFalse($treatment->isActive());
    }

    public function testGetCreatedAtIsSetAtConstruction(): void
    {
        $before = new \DateTimeImmutable();

        $treatment = new Treatment($this->createUser(), 'Doliprane', '1000mg', $this->createUser());

        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $treatment->getCreatedAt());
        self::assertLessThanOrEqual($after, $treatment->getCreatedAt());
    }
}
