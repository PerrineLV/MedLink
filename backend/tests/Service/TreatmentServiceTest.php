<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Treatment;
use App\Entity\TreatmentSchedule;
use App\Entity\User;
use App\Exception\InvalidTreatmentException;
use App\Repository\PatientSoignantRepository;
use App\Service\TreatmentService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class TreatmentServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private PatientSoignantRepository&Stub $patientSoignantRepository;
    private Security&Stub $security;
    private TreatmentService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->patientSoignantRepository = $this->createStub(PatientSoignantRepository::class);
        $this->security = $this->createStub(Security::class);
        $this->service = new TreatmentService(
            $this->entityManager,
            $this->patientSoignantRepository,
            $this->security,
        );
    }

    public function testCreatePersistsForAnActivelyAttachedSoignant(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);

        $this->security->method('getUser')->willReturn($soignant);
        $this->patientSoignantRepository->method('hasActiveRelation')->willReturn(true);

        // Un persist pour le Treatment, un pour son unique TreatmentSchedule.
        $this->entityManager->expects(self::exactly(2))->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $treatment = $this->service->create($patient, 'Bisoprolol', '5 mg', [['moment' => TreatmentSchedule::MOMENT_MORNING]]);

        self::assertSame($patient, $treatment->getPatient());
        self::assertSame($soignant, $treatment->getPrescribedBy());
        self::assertSame('Bisoprolol', $treatment->getName());
        self::assertSame('5 mg', $treatment->getDosage());
        self::assertCount(1, $treatment->getSchedules());
        self::assertSame(TreatmentSchedule::MOMENT_MORNING, $treatment->getSchedules()[0]->getMoment());
        self::assertNull($treatment->getSchedules()[0]->getCustomLabel());
        self::assertTrue($treatment->isActive());
    }

    public function testCreatePersistsAScheduleForEachMomentOfDay(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);

        $this->security->method('getUser')->willReturn($soignant);
        $this->patientSoignantRepository->method('hasActiveRelation')->willReturn(true);

        // Un persist pour le Treatment, trois pour ses TreatmentSchedule.
        $this->entityManager->expects(self::exactly(4))->method('persist');

        $treatment = $this->service->create($patient, 'Bisoprolol', '5 mg', [
            ['moment' => TreatmentSchedule::MOMENT_MORNING],
            ['moment' => TreatmentSchedule::MOMENT_NOON],
            ['moment' => TreatmentSchedule::MOMENT_EVENING],
        ]);

        $moments = array_map(
            static fn (TreatmentSchedule $schedule) => $schedule->getMoment(),
            $treatment->getSchedules()->toArray(),
        );

        self::assertSame(
            [TreatmentSchedule::MOMENT_MORNING, TreatmentSchedule::MOMENT_NOON, TreatmentSchedule::MOMENT_EVENING],
            $moments,
        );
    }

    public function testCreatePersistsACustomScheduleWithItsLabel(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);

        $this->security->method('getUser')->willReturn($soignant);
        $this->patientSoignantRepository->method('hasActiveRelation')->willReturn(true);

        // Un persist pour le Treatment, un pour son unique TreatmentSchedule.
        $this->entityManager->expects(self::exactly(2))->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $treatment = $this->service->create($patient, 'Ramipril', '10 mg', [
            ['moment' => TreatmentSchedule::MOMENT_CUSTOM, 'label' => 'Avant le coucher'],
        ]);

        self::assertSame(TreatmentSchedule::MOMENT_CUSTOM, $treatment->getSchedules()[0]->getMoment());
        self::assertSame('Avant le coucher', $treatment->getSchedules()[0]->getCustomLabel());
    }

    public function testCreateThrowsAccessDeniedWhenNoUserIsAuthenticated(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);

        $this->security->method('getUser')->willReturn(null);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(AccessDeniedException::class);

        $this->service->create($patient, 'Bisoprolol', '5 mg', [['moment' => TreatmentSchedule::MOMENT_MORNING]]);
    }

    public function testCreateThrowsAccessDeniedForThePatientThemselves(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);

        $this->security->method('getUser')->willReturn($patient);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(AccessDeniedException::class);

        $this->service->create($patient, 'Bisoprolol', '5 mg', [['moment' => TreatmentSchedule::MOMENT_MORNING]]);
    }

    public function testCreateThrowsAccessDeniedForAnAidant(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $aidant = $this->makeUser(2, User::ROLE_AIDANT);

        $this->security->method('getUser')->willReturn($aidant);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(AccessDeniedException::class);

        $this->service->create($patient, 'Bisoprolol', '5 mg', [['moment' => TreatmentSchedule::MOMENT_MORNING]]);
    }

    public function testCreateThrowsAccessDeniedWhenTheSoignantHasNoActiveRelation(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);

        $this->security->method('getUser')->willReturn($soignant);
        $this->patientSoignantRepository->method('hasActiveRelation')->willReturn(false);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(AccessDeniedException::class);

        $this->service->create($patient, 'Bisoprolol', '5 mg', [['moment' => TreatmentSchedule::MOMENT_MORNING]]);
    }

    #[DataProvider('provideInvalidTreatmentData')]
    public function testCreateRejectsInvalidData(string $name, string $dosage, array $schedules): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);

        $this->security->method('getUser')->willReturn($soignant);
        $this->patientSoignantRepository->method('hasActiveRelation')->willReturn(true);

        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(InvalidTreatmentException::class);

        $this->service->create($patient, $name, $dosage, $schedules);
    }

    /**
     * @return iterable<string, array{string, string, list<array{moment: string, label?: ?string}>}>
     */
    public static function provideInvalidTreatmentData(): iterable
    {
        yield 'empty name' => ['   ', '5 mg', [['moment' => TreatmentSchedule::MOMENT_MORNING]]];
        yield 'empty dosage' => ['Bisoprolol', '   ', [['moment' => TreatmentSchedule::MOMENT_MORNING]]];
        yield 'unknown moment' => ['Bisoprolol', '5 mg', [['moment' => 'afternoon']]];
        yield 'no schedule at all' => ['Bisoprolol', '5 mg', []];
        yield 'duplicate morning moment' => ['Bisoprolol', '5 mg', [
            ['moment' => TreatmentSchedule::MOMENT_MORNING],
            ['moment' => TreatmentSchedule::MOMENT_MORNING],
        ]];
        yield 'custom moment without a label' => ['Bisoprolol', '5 mg', [['moment' => TreatmentSchedule::MOMENT_CUSTOM]]];
        yield 'custom moment with a blank label' => ['Bisoprolol', '5 mg', [['moment' => TreatmentSchedule::MOMENT_CUSTOM, 'label' => '   ']]];
        yield 'custom moment with a too long label' => ['Bisoprolol', '5 mg', [['moment' => TreatmentSchedule::MOMENT_CUSTOM, 'label' => str_repeat('a', 101)]]];
    }

    public function testUpdatePersistsPartialChangesForAnActivelyAttachedSoignant(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $treatment = new Treatment($patient, 'Bisoprolol', '5 mg', $soignant);

        $this->security->method('getUser')->willReturn($soignant);
        $this->patientSoignantRepository->method('hasActiveRelation')->willReturn(true);

        $this->entityManager->expects(self::once())->method('flush');
        $this->entityManager->expects(self::never())->method('persist');

        $updated = $this->service->update($treatment, null, null, null, false);

        self::assertSame('Bisoprolol', $updated->getName());
        self::assertFalse($updated->isActive());
    }

    public function testUpdateThrowsAccessDeniedWhenTheSoignantHasNoActiveRelation(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $treatment = new Treatment($patient, 'Bisoprolol', '5 mg', $soignant);

        $this->security->method('getUser')->willReturn($soignant);
        $this->patientSoignantRepository->method('hasActiveRelation')->willReturn(false);

        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(AccessDeniedException::class);

        $this->service->update($treatment, null, null, null, false);
    }

    private function makeUser(int $id, string $role): User
    {
        $user = new User(sprintf('user-%d@medlink.test', $id), 'Prenom', 'Nom');
        $user->setRoles([$role]);

        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }
}
