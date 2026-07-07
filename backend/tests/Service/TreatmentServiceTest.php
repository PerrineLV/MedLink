<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Treatment;
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

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $treatment = $this->service->create($patient, 'Bisoprolol', '5 mg', '08:00');

        self::assertSame($patient, $treatment->getPatient());
        self::assertSame($soignant, $treatment->getPrescribedBy());
        self::assertSame('Bisoprolol', $treatment->getName());
        self::assertSame('5 mg', $treatment->getDosage());
        self::assertSame('08:00', $treatment->getScheduledTime());
        self::assertTrue($treatment->isActive());
    }

    public function testCreateThrowsAccessDeniedWhenNoUserIsAuthenticated(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);

        $this->security->method('getUser')->willReturn(null);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(AccessDeniedException::class);

        $this->service->create($patient, 'Bisoprolol', '5 mg', '08:00');
    }

    public function testCreateThrowsAccessDeniedForThePatientThemselves(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);

        $this->security->method('getUser')->willReturn($patient);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(AccessDeniedException::class);

        $this->service->create($patient, 'Bisoprolol', '5 mg', '08:00');
    }

    public function testCreateThrowsAccessDeniedForAnAidant(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $aidant = $this->makeUser(2, User::ROLE_AIDANT);

        $this->security->method('getUser')->willReturn($aidant);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(AccessDeniedException::class);

        $this->service->create($patient, 'Bisoprolol', '5 mg', '08:00');
    }

    public function testCreateThrowsAccessDeniedWhenTheSoignantHasNoActiveRelation(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);

        $this->security->method('getUser')->willReturn($soignant);
        $this->patientSoignantRepository->method('hasActiveRelation')->willReturn(false);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(AccessDeniedException::class);

        $this->service->create($patient, 'Bisoprolol', '5 mg', '08:00');
    }

    #[DataProvider('provideInvalidTreatmentData')]
    public function testCreateRejectsInvalidData(string $name, string $dosage, string $scheduledTime): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);

        $this->security->method('getUser')->willReturn($soignant);
        $this->patientSoignantRepository->method('hasActiveRelation')->willReturn(true);

        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(InvalidTreatmentException::class);

        $this->service->create($patient, $name, $dosage, $scheduledTime);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideInvalidTreatmentData(): iterable
    {
        yield 'empty name' => ['   ', '5 mg', '08:00'];
        yield 'empty dosage' => ['Bisoprolol', '   ', '08:00'];
        yield 'scheduled time without colon' => ['Bisoprolol', '5 mg', '0800'];
        yield 'scheduled time out of range' => ['Bisoprolol', '5 mg', '25:00'];
        yield 'scheduled time with invalid minutes' => ['Bisoprolol', '5 mg', '08:60'];
    }

    public function testUpdatePersistsPartialChangesForAnActivelyAttachedSoignant(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $treatment = new Treatment($patient, 'Bisoprolol', '5 mg', '08:00', $soignant);

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
        $treatment = new Treatment($patient, 'Bisoprolol', '5 mg', '08:00', $soignant);

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
