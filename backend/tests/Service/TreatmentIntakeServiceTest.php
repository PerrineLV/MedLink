<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Treatment;
use App\Entity\TreatmentIntake;
use App\Entity\TreatmentSchedule;
use App\Entity\User;
use App\Repository\TreatmentIntakeRepository;
use App\Service\TreatmentIntakeService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class TreatmentIntakeServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private TreatmentIntakeRepository&Stub $treatmentIntakeRepository;
    private TreatmentIntakeService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->treatmentIntakeRepository = $this->createStub(TreatmentIntakeRepository::class);
        $this->service = new TreatmentIntakeService($this->entityManager, $this->treatmentIntakeRepository);
    }

    public function testFindOrCreateForDateReturnsTheExistingIntakeWithoutPersisting(): void
    {
        $schedule = $this->makeSchedule();
        $date = new \DateTimeImmutable('today');
        $existing = new TreatmentIntake($schedule, $date);

        $this->treatmentIntakeRepository->method('findOneByScheduleAndDate')->willReturn($existing);

        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::never())->method('flush');

        $result = $this->service->findOrCreateForDate($schedule, $date);

        self::assertSame($existing, $result);
    }

    public function testFindOrCreateForDateCreatesANewNotTakenIntakeWhenAbsent(): void
    {
        $schedule = $this->makeSchedule();
        $date = new \DateTimeImmutable('today');

        $this->treatmentIntakeRepository->method('findOneByScheduleAndDate')->willReturn(null);

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $intake = $this->service->findOrCreateForDate($schedule, $date);

        self::assertSame($schedule, $intake->getSchedule());
        self::assertEquals($date, $intake->getDate());
        self::assertFalse($intake->isTaken());
        self::assertNull($intake->getTakenAt());
    }

    public function testToggleMarksAnUntakenIntakeAsTaken(): void
    {
        $intake = new TreatmentIntake($this->makeSchedule(), new \DateTimeImmutable('today'));

        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->service->toggle($intake);

        self::assertTrue($result->isTaken());
        self::assertNotNull($result->getTakenAt());
    }

    public function testToggleMarksATakenIntakeAsNotTaken(): void
    {
        $intake = new TreatmentIntake($this->makeSchedule(), new \DateTimeImmutable('today'));
        $intake->markTaken(new \DateTimeImmutable('today 08:00'));

        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->service->toggle($intake);

        self::assertFalse($result->isTaken());
        self::assertNull($result->getTakenAt());
    }

    private function makeSchedule(): TreatmentSchedule
    {
        $patient = new User('patient@medlink.test', 'Prenom', 'Nom');
        $patient->setRoles([User::ROLE_PATIENT]);

        $soignant = new User('soignant@medlink.test', 'Prenom', 'Nom');
        $soignant->setRoles([User::ROLE_SOIGNANT]);

        $treatment = new Treatment($patient, 'Bisoprolol', '5 mg', $soignant);

        return new TreatmentSchedule($treatment, TreatmentSchedule::MOMENT_MORNING);
    }
}
