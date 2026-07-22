<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Treatment;
use App\Entity\TreatmentIntake;
use App\Entity\TreatmentSchedule;
use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TreatmentScheduleTest extends TestCase
{
    private function createTreatment(): Treatment
    {
        $user = new User('user@medlink.test', 'Jeanne', 'Dupont');

        return new Treatment($user, 'Doliprane', '1000mg', $user);
    }

    public function testConstructorSetsBaseFields(): void
    {
        $treatment = $this->createTreatment();

        $schedule = new TreatmentSchedule($treatment, TreatmentSchedule::MOMENT_MORNING, 'Au réveil');

        self::assertSame($treatment, $schedule->getTreatment());
        self::assertSame(TreatmentSchedule::MOMENT_MORNING, $schedule->getMoment());
        self::assertSame('Au réveil', $schedule->getCustomLabel());
        self::assertNull($schedule->getId());
        self::assertNull($schedule->getTodayIntake());
    }

    public function testConstructorDefaultsCustomLabelToNull(): void
    {
        $schedule = new TreatmentSchedule($this->createTreatment(), TreatmentSchedule::MOMENT_NOON);

        self::assertNull($schedule->getCustomLabel());
    }

    #[DataProvider('momentProvider')]
    public function testConstructorAcceptsAllMoments(string $moment): void
    {
        $schedule = new TreatmentSchedule($this->createTreatment(), $moment);

        self::assertSame($moment, $schedule->getMoment());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function momentProvider(): iterable
    {
        yield 'morning' => [TreatmentSchedule::MOMENT_MORNING];
        yield 'noon' => [TreatmentSchedule::MOMENT_NOON];
        yield 'evening' => [TreatmentSchedule::MOMENT_EVENING];
        yield 'custom' => [TreatmentSchedule::MOMENT_CUSTOM];
    }

    public function testSetTodayIntakeUpdatesValue(): void
    {
        $schedule = new TreatmentSchedule($this->createTreatment(), TreatmentSchedule::MOMENT_MORNING);
        $intake = new TreatmentIntake($schedule, new \DateTimeImmutable());

        $schedule->setTodayIntake($intake);

        self::assertSame($intake, $schedule->getTodayIntake());
    }

    public function testSetTodayIntakeAcceptsNull(): void
    {
        $schedule = new TreatmentSchedule($this->createTreatment(), TreatmentSchedule::MOMENT_MORNING);
        $schedule->setTodayIntake(new TreatmentIntake($schedule, new \DateTimeImmutable()));

        $schedule->setTodayIntake(null);

        self::assertNull($schedule->getTodayIntake());
    }
}
