<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Treatment;
use App\Entity\TreatmentIntake;
use App\Entity\TreatmentSchedule;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class TreatmentIntakeTest extends TestCase
{
    private function createSchedule(): TreatmentSchedule
    {
        $user = new User('user@medlink.test', 'Jeanne', 'Dupont');
        $treatment = new Treatment($user, 'Doliprane', '1000mg', $user);

        return new TreatmentSchedule($treatment, TreatmentSchedule::MOMENT_MORNING);
    }

    public function testConstructorSetsBaseFieldsAndDefaultsToNotTaken(): void
    {
        $schedule = $this->createSchedule();
        $date = new \DateTimeImmutable('2026-07-22');

        $intake = new TreatmentIntake($schedule, $date);

        self::assertSame($schedule, $intake->getSchedule());
        self::assertSame($date, $intake->getDate());
        self::assertFalse($intake->isTaken());
        self::assertNull($intake->getTakenAt());
        self::assertNull($intake->getId());
        self::assertNull($intake->getScheduleId());
    }

    public function testGetTreatmentReturnsTreatmentThroughSchedule(): void
    {
        $schedule = $this->createSchedule();
        $intake = new TreatmentIntake($schedule, new \DateTimeImmutable());

        self::assertSame($schedule->getTreatment(), $intake->getTreatment());
    }

    public function testMarkTakenSetsTakenAndTakenAt(): void
    {
        $intake = new TreatmentIntake($this->createSchedule(), new \DateTimeImmutable());
        $takenAt = new \DateTimeImmutable();

        $intake->markTaken($takenAt);

        self::assertTrue($intake->isTaken());
        self::assertSame($takenAt, $intake->getTakenAt());
    }

    public function testMarkNotTakenResetsTakenAndTakenAt(): void
    {
        $intake = new TreatmentIntake($this->createSchedule(), new \DateTimeImmutable());
        $intake->markTaken(new \DateTimeImmutable());

        $intake->markNotTaken();

        self::assertFalse($intake->isTaken());
        self::assertNull($intake->getTakenAt());
    }
}
