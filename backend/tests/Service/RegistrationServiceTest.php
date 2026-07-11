<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\RegistrationInput;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\RegistrationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RegistrationServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private UserRepository&Stub $userRepository;
    private UserPasswordHasherInterface&Stub $passwordHasher;
    private RegistrationService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userRepository = $this->createStub(UserRepository::class);
        $this->passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed-password');

        $this->service = new RegistrationService(
            $this->entityManager,
            $this->userRepository,
            $this->passwordHasher,
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideRoles(): iterable
    {
        yield 'patient' => ['patient', User::ROLE_PATIENT];
        yield 'aidant' => ['aidant', User::ROLE_AIDANT];
        yield 'soignant' => ['soignant', User::ROLE_SOIGNANT];
    }

    #[DataProvider('provideRoles')]
    public function testRegisterCreatesAnAccountForEachAllowedRole(string $requestedRole, string $expectedRole): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $user = $this->service->register($this->makeInput(role: $requestedRole));

        self::assertSame([$expectedRole], $user->getRoles());
        self::assertSame('nouveau@medlink.test', $user->getEmail());
    }

    public function testTitleIsPersistedOnlyForSoignant(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $soignant = $this->service->register($this->makeInput(role: 'soignant', title: 'Dr'));

        self::assertSame('Dr', $soignant->getTitle());
    }

    public function testTitleIsIgnoredSilentlyForNonSoignantRoles(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $patient = $this->service->register($this->makeInput(role: 'patient', title: 'Dr'));

        self::assertNull($patient->getTitle());
    }

    public function testConsentAtIsRecordedOnSuccessfulRegistration(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $user = $this->service->register($this->makeInput());

        self::assertNotNull($user->getConsentAt());
    }

    public function testPasswordIsHashedNotStoredInPlainText(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $user = $this->service->register($this->makeInput(password: 'PlainPass1'));

        self::assertSame('hashed-password', $user->getPassword());
    }

    public function testRegisterThrowsConflictWhenEmailAlreadyExists(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(
            new User('nouveau@medlink.test', 'Existant', 'Existant'),
        );

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(ConflictHttpException::class);

        $this->service->register($this->makeInput());
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
    public function testRegisterThrowsBadRequestForWeakPassword(string $weakPassword): void
    {
        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(BadRequestHttpException::class);

        $this->service->register($this->makeInput(password: $weakPassword));
    }

    public function testRegisterThrowsBadRequestForAdminRole(): void
    {
        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(BadRequestHttpException::class);

        $this->service->register($this->makeInput(role: 'admin'));
    }

    public function testRegisterThrowsBadRequestForUnknownRole(): void
    {
        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(BadRequestHttpException::class);

        $this->service->register($this->makeInput(role: 'invite'));
    }

    public function testRegisterThrowsBadRequestForInvalidEmail(): void
    {
        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(BadRequestHttpException::class);

        $this->service->register($this->makeInput(email: 'not-an-email'));
    }

    public function testRegisterThrowsBadRequestWhenConsentIsMissing(): void
    {
        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(BadRequestHttpException::class);

        $this->service->register($this->makeInput(consent: false));
    }

    private function makeInput(
        string $email = 'nouveau@medlink.test',
        string $password = 'ValidPass1',
        string $firstName = 'Jeanne',
        string $lastName = 'Dupont',
        string $role = 'patient',
        ?string $title = null,
        bool $consent = true,
    ): RegistrationInput {
        return new RegistrationInput($email, $password, $firstName, $lastName, $role, $title, $consent);
    }
}
