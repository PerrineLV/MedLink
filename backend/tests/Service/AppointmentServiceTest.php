<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Appointment;
use App\Entity\User;
use App\Exception\InvalidAppointmentException;
use App\Repository\PatientSoignantRepository;
use App\Service\AppointmentService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AppointmentServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private PatientSoignantRepository&Stub $patientSoignantRepository;
    private Security&Stub $security;
    private AppointmentService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->patientSoignantRepository = $this->createStub(PatientSoignantRepository::class);
        $this->security = $this->createStub(Security::class);
        $this->service = new AppointmentService(
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

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $scheduledAt = new \DateTimeImmutable('+3 days');
        $appointment = $this->service->create($patient, $scheduledAt, 'Contrôle de routine.');

        self::assertSame($patient, $appointment->getPatient());
        self::assertSame($soignant, $appointment->getSoignant());
        self::assertSame($scheduledAt, $appointment->getScheduledAt());
        self::assertSame('Contrôle de routine.', $appointment->getNotes());
        self::assertSame(Appointment::STATUS_PLANNED, $appointment->getStatus());
    }

    public function testCreateThrowsAccessDeniedWhenNoUserIsAuthenticated(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);

        $this->security->method('getUser')->willReturn(null);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(AccessDeniedException::class);

        $this->service->create($patient, new \DateTimeImmutable('+1 day'), null);
    }

    public function testCreateThrowsAccessDeniedForThePatientThemselves(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);

        $this->security->method('getUser')->willReturn($patient);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(AccessDeniedException::class);

        $this->service->create($patient, new \DateTimeImmutable('+1 day'), null);
    }

    public function testCreateThrowsAccessDeniedForAnAidant(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $aidant = $this->makeUser(2, User::ROLE_AIDANT);

        $this->security->method('getUser')->willReturn($aidant);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(AccessDeniedException::class);

        $this->service->create($patient, new \DateTimeImmutable('+1 day'), null);
    }

    public function testCreateThrowsAccessDeniedWhenTheSoignantHasNoActiveRelation(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);

        $this->security->method('getUser')->willReturn($soignant);
        $this->patientSoignantRepository->method('hasActiveRelation')->willReturn(false);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(AccessDeniedException::class);

        $this->service->create($patient, new \DateTimeImmutable('+1 day'), null);
    }

    public function testCreateRejectsAPastDate(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);

        $this->security->method('getUser')->willReturn($soignant);
        $this->patientSoignantRepository->method('hasActiveRelation')->willReturn(true);

        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(InvalidAppointmentException::class);

        $this->service->create($patient, new \DateTimeImmutable('-1 day'), null);
    }

    public function testCreateRejectsNotesThatAreTooLong(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);

        $this->security->method('getUser')->willReturn($soignant);
        $this->patientSoignantRepository->method('hasActiveRelation')->willReturn(true);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(InvalidAppointmentException::class);

        $this->service->create($patient, new \DateTimeImmutable('+1 day'), str_repeat('a', 1001));
    }

    public function testUpdatePersistsPartialChangesForAnActivelyAttachedSoignant(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $appointment = new Appointment($patient, $soignant, new \DateTimeImmutable('+3 days'));

        $this->security->method('getUser')->willReturn($soignant);
        $this->patientSoignantRepository->method('hasActiveRelation')->willReturn(true);

        $this->entityManager->expects(self::once())->method('flush');
        $this->entityManager->expects(self::never())->method('persist');

        $updated = $this->service->update($appointment, null, Appointment::STATUS_CANCELLED, null);

        self::assertSame(Appointment::STATUS_CANCELLED, $updated->getStatus());
    }

    public function testUpdateAllowsCancellingAnAppointmentWhoseDateHasAlreadyPassed(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $appointment = new Appointment($patient, $soignant, new \DateTimeImmutable('-1 day'));

        $this->security->method('getUser')->willReturn($soignant);
        $this->patientSoignantRepository->method('hasActiveRelation')->willReturn(true);

        $this->entityManager->expects(self::once())->method('flush');

        $updated = $this->service->update($appointment, null, Appointment::STATUS_CANCELLED, null);

        self::assertSame(Appointment::STATUS_CANCELLED, $updated->getStatus());
    }

    public function testUpdateRejectsARescheduleToAPastDate(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $appointment = new Appointment($patient, $soignant, new \DateTimeImmutable('+3 days'));

        $this->security->method('getUser')->willReturn($soignant);
        $this->patientSoignantRepository->method('hasActiveRelation')->willReturn(true);

        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(InvalidAppointmentException::class);

        $this->service->update($appointment, new \DateTimeImmutable('-1 day'), null, null);
    }

    public function testUpdateRejectsAnUnknownStatus(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $appointment = new Appointment($patient, $soignant, new \DateTimeImmutable('+3 days'));

        $this->security->method('getUser')->willReturn($soignant);
        $this->patientSoignantRepository->method('hasActiveRelation')->willReturn(true);

        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(InvalidAppointmentException::class);

        $this->service->update($appointment, null, 'unknown', null);
    }

    public function testUpdateThrowsAccessDeniedWhenTheSoignantHasNoActiveRelation(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $appointment = new Appointment($patient, $soignant, new \DateTimeImmutable('+3 days'));

        $this->security->method('getUser')->willReturn($soignant);
        $this->patientSoignantRepository->method('hasActiveRelation')->willReturn(false);

        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(AccessDeniedException::class);

        $this->service->update($appointment, null, Appointment::STATUS_CANCELLED, null);
    }

    public function testDeleteRemovesForAnActivelyAttachedSoignant(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $appointment = new Appointment($patient, $soignant, new \DateTimeImmutable('+3 days'));

        $this->security->method('getUser')->willReturn($soignant);
        $this->patientSoignantRepository->method('hasActiveRelation')->willReturn(true);

        $this->entityManager->expects(self::once())->method('remove')->with($appointment);
        $this->entityManager->expects(self::once())->method('flush');

        $this->service->delete($appointment);
    }

    public function testDeleteThrowsAccessDeniedWhenTheSoignantHasNoActiveRelation(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $appointment = new Appointment($patient, $soignant, new \DateTimeImmutable('+3 days'));

        $this->security->method('getUser')->willReturn($soignant);
        $this->patientSoignantRepository->method('hasActiveRelation')->willReturn(false);

        $this->entityManager->expects(self::never())->method('remove');

        $this->expectException(AccessDeniedException::class);

        $this->service->delete($appointment);
    }

    private function makeUser(int $id, string $role): User
    {
        $user = new User(sprintf('user-%d@medlink.test', $id), 'Prenom', 'Nom');
        $user->setRoles([$role]);

        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }
}
