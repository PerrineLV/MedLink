<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\JournalEntry;
use App\Entity\PatientAidant;
use App\Entity\PatientSoignant;
use App\Entity\RefreshToken;
use App\Entity\Treatment;
use App\Entity\User;
use App\Repository\JournalEntryRepository;
use App\Repository\PatientAidantRepository;
use App\Repository\PatientSoignantRepository;
use App\Repository\TreatmentRepository;
use App\Repository\UserRepository;
use App\Service\AccountService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AccountServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private UserPasswordHasherInterface&Stub $passwordHasher;
    private JournalEntryRepository&Stub $journalEntryRepository;
    private TreatmentRepository&Stub $treatmentRepository;
    private PatientAidantRepository&Stub $patientAidantRepository;
    private PatientSoignantRepository&Stub $patientSoignantRepository;
    private UserRepository&Stub $userRepository;
    private AccountService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed-password');
        $this->journalEntryRepository = $this->createStub(JournalEntryRepository::class);
        $this->treatmentRepository = $this->createStub(TreatmentRepository::class);
        $this->patientAidantRepository = $this->createStub(PatientAidantRepository::class);
        $this->patientSoignantRepository = $this->createStub(PatientSoignantRepository::class);
        $this->userRepository = $this->createStub(UserRepository::class);

        $this->service = new AccountService(
            $this->entityManager,
            $this->passwordHasher,
            $this->journalEntryRepository,
            $this->treatmentRepository,
            $this->patientAidantRepository,
            $this->patientSoignantRepository,
            $this->userRepository,
        );
    }

    public function testChangePasswordHashesAndPersistsTheNewPassword(): void
    {
        $user = $this->makeUser(1, User::ROLE_PATIENT);
        $this->passwordHasher->method('isPasswordValid')->willReturn(true);

        $this->entityManager->expects(self::once())->method('flush');

        $this->service->changePassword($user, 'CurrentPass1', 'NewValidPass1');

        self::assertSame('hashed-password', $user->getPassword());
    }

    public function testChangePasswordThrowsAccessDeniedWhenCurrentPasswordIsWrong(): void
    {
        $user = $this->makeUser(1, User::ROLE_PATIENT);
        $this->passwordHasher->method('isPasswordValid')->willReturn(false);

        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(AccessDeniedHttpException::class);

        $this->service->changePassword($user, 'WrongPass1', 'NewValidPass1');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideWeakPasswords(): iterable
    {
        yield 'too short' => ['Ab1'];
        yield 'no digit' => ['abcdefgh'];
        yield 'no letter' => ['12345678'];
    }

    #[DataProvider('provideWeakPasswords')]
    public function testChangePasswordThrowsBadRequestForWeakNewPassword(string $weakPassword): void
    {
        $user = $this->makeUser(1, User::ROLE_PATIENT);
        $this->passwordHasher->method('isPasswordValid')->willReturn(true);

        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(BadRequestHttpException::class);

        $this->service->changePassword($user, 'CurrentPass1', $weakPassword);
    }

    public function testExportDataForPatientIncludesJournalEntriesAndTreatments(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $entry = new JournalEntry($patient, $patient, 3, 4, '120/80', 'RAS');
        $treatment = new Treatment($patient, 'Doliprane', '1g', $patient);

        $this->journalEntryRepository->method('findByPatientIds')->willReturn([$entry]);
        $this->treatmentRepository->method('findByPatientIds')->willReturn([$treatment]);

        $data = $this->service->exportData($patient);

        self::assertSame('patient-1@medlink.test', $data['account']['email']);
        self::assertCount(1, $data['journalEntries']);
        self::assertSame(3, $data['journalEntries'][0]['mood']);
        self::assertCount(1, $data['treatments']);
        self::assertSame('Doliprane', $data['treatments'][0]['name']);
        self::assertArrayNotHasKey('liaisons', $data);
    }

    public function testExportDataForAidantIncludesLiaisonsButNoMedicalData(): void
    {
        $aidant = $this->makeUser(1, User::ROLE_AIDANT);
        $patient = $this->makeUser(2, User::ROLE_PATIENT);
        $relation = new PatientAidant($patient, $aidant);

        $this->patientAidantRepository->method('findForAidant')->willReturn([$relation]);

        $data = $this->service->exportData($aidant);

        self::assertArrayNotHasKey('journalEntries', $data);
        self::assertCount(1, $data['liaisons']);
        self::assertSame(2, $data['liaisons'][0]['patientId']);
    }

    public function testExportDataForSoignantIncludesLiaisons(): void
    {
        $soignant = $this->makeUser(1, User::ROLE_SOIGNANT);
        $patient = $this->makeUser(2, User::ROLE_PATIENT);
        $relation = new PatientSoignant($patient, $soignant);

        $this->patientSoignantRepository->method('findForSoignant')->willReturn([$relation]);

        $data = $this->service->exportData($soignant);

        self::assertCount(1, $data['liaisons']);
        self::assertSame(2, $data['liaisons'][0]['patientId']);
    }

    public function testChangeEmailThrowsAccessDeniedWhenPasswordIsWrong(): void
    {
        $user = $this->makeUser(1, User::ROLE_PATIENT);
        $this->passwordHasher->method('isPasswordValid')->willReturn(false);

        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(AccessDeniedHttpException::class);

        $this->service->changeEmail($user, 'WrongPass1', 'nouveau@medlink.test');
    }

    public function testChangeEmailThrowsBadRequestForInvalidEmail(): void
    {
        $user = $this->makeUser(1, User::ROLE_PATIENT);
        $this->passwordHasher->method('isPasswordValid')->willReturn(true);

        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(BadRequestHttpException::class);

        $this->service->changeEmail($user, 'CurrentPass1', 'pas-un-email');
    }

    public function testChangeEmailThrowsConflictWhenEmailAlreadyUsedByAnotherAccount(): void
    {
        $user = $this->makeUser(1, User::ROLE_PATIENT);
        $other = $this->makeUser(2, User::ROLE_PATIENT);
        $this->passwordHasher->method('isPasswordValid')->willReturn(true);
        $this->userRepository->method('findOneBy')->willReturn($other);

        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(ConflictHttpException::class);

        $this->service->changeEmail($user, 'CurrentPass1', 'aidant-2@medlink.test');
    }

    public function testChangeEmailUpdatesTheEmailAndRevokesRefreshTokens(): void
    {
        $user = $this->makeUser(1, User::ROLE_PATIENT);
        $originalEmail = $user->getEmail();
        $this->passwordHasher->method('isPasswordValid')->willReturn(true);
        $this->userRepository->method('findOneBy')->willReturn(null);

        $refreshToken = new RefreshToken();
        $refreshToken->setUsername($originalEmail);

        $refreshTokenRepository = $this->createStub(EntityRepository::class);
        $refreshTokenRepository->method('findBy')->with(['username' => $originalEmail])->willReturn([$refreshToken]);

        $this->entityManager->method('getRepository')->willReturn($refreshTokenRepository);
        $this->entityManager->expects(self::once())->method('remove')->with($refreshToken);
        $this->entityManager->expects(self::once())->method('flush');

        $this->service->changeEmail($user, 'CurrentPass1', 'nouveau@medlink.test');

        self::assertSame('nouveau@medlink.test', $user->getEmail());
    }

    public function testChangeEmailAllowsKeepingTheSameEmailForTheSameAccount(): void
    {
        $user = $this->makeUser(1, User::ROLE_PATIENT);
        $this->passwordHasher->method('isPasswordValid')->willReturn(true);
        $this->userRepository->method('findOneBy')->willReturn($user);

        $refreshTokenRepository = $this->createStub(EntityRepository::class);
        $refreshTokenRepository->method('findBy')->willReturn([]);
        $this->entityManager->method('getRepository')->willReturn($refreshTokenRepository);
        $this->entityManager->expects(self::once())->method('flush');

        $this->service->changeEmail($user, 'CurrentPass1', $user->getEmail());

        self::assertSame('patient-1@medlink.test', $user->getEmail());
    }

    public function testDeleteAccountThrowsAccessDeniedWhenPasswordIsWrong(): void
    {
        $user = $this->makeUser(1, User::ROLE_PATIENT);
        $this->passwordHasher->method('isPasswordValid')->willReturn(false);

        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(AccessDeniedHttpException::class);

        $this->service->deleteAccount($user, 'WrongPass1');
    }

    public function testDeleteAccountAnonymizesTheUserAndRevokesRefreshTokens(): void
    {
        $user = $this->makeUser(1, User::ROLE_PATIENT);
        $originalEmail = $user->getEmail();
        $this->passwordHasher->method('isPasswordValid')->willReturn(true);

        $refreshToken = new RefreshToken();
        $refreshToken->setUsername($originalEmail);

        $refreshTokenRepository = $this->createStub(EntityRepository::class);
        $refreshTokenRepository->method('findBy')->with(['username' => $originalEmail])->willReturn([$refreshToken]);

        $this->entityManager->method('getRepository')->willReturn($refreshTokenRepository);
        $this->entityManager->expects(self::once())->method('remove')->with($refreshToken);
        $this->entityManager->expects(self::once())->method('flush');

        $this->service->deleteAccount($user, 'CurrentPass1');

        self::assertNotSame($originalEmail, $user->getEmail());
        self::assertSame('Utilisateur', $user->getFirstName());
        self::assertSame('Supprimé', $user->getLastName());
        self::assertNull($user->getTitle());
        self::assertNotNull($user->getDeletedAt());
    }

    private function makeUser(int $id, string $role): User
    {
        $label = match ($role) {
            User::ROLE_PATIENT => 'patient',
            User::ROLE_AIDANT => 'aidant',
            User::ROLE_SOIGNANT => 'soignant',
            default => 'user',
        };

        $user = new User(sprintf('%s-%d@medlink.test', $label, $id), 'Prenom', 'Nom');
        $user->setRoles([$role]);

        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }
}
