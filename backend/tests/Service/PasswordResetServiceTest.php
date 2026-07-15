<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\PasswordResetToken;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use App\Service\PasswordResetMailer;
use App\Service\PasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PasswordResetServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private UserRepository&Stub $userRepository;
    private PasswordResetTokenRepository&Stub $passwordResetTokenRepository;
    private UserPasswordHasherInterface&Stub $passwordHasher;
    private MailerInterface&Stub $mailer;
    private PasswordResetService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userRepository = $this->createStub(UserRepository::class);
        $this->passwordResetTokenRepository = $this->createStub(PasswordResetTokenRepository::class);
        $this->passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed-new-password');
        $this->mailer = $this->createStub(MailerInterface::class);

        $this->service = $this->makeService($this->mailer);
    }

    public function testRequestResetSendsAnEmailWhenTheAccountExists(): void
    {
        $user = $this->makeUser(1, 'patient@medlink.test');
        $this->userRepository->method('findOneBy')->willReturn($user);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send');

        $this->entityManager->expects(self::once())->method('persist')
            ->with(self::isInstanceOf(PasswordResetToken::class));
        $this->entityManager->expects(self::once())->method('flush');

        $this->makeService($mailer)->requestReset('patient@medlink.test');
    }

    public function testRequestResetForwardsThePlatformToTheMailer(): void
    {
        $user = $this->makeUser(1, 'patient@medlink.test');
        $this->userRepository->method('findOneBy')->willReturn($user);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(fn (Email $email) => str_contains((string) $email->getTextBody(), 'medlink://')));

        $this->makeService($mailer)->requestReset('patient@medlink.test', PasswordResetMailer::PLATFORM_MOBILE);
    }

    public function testRequestResetDoesNothingWhenTheAccountDoesNotExist(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::never())->method('flush');

        $this->makeService($mailer)->requestReset('inconnu@medlink.test');
    }

    public function testRequestResetDoesNothingWhenTheAccountIsInactive(): void
    {
        $user = $this->makeUser(1, 'patient@medlink.test');
        $user->setActive(false);
        $this->userRepository->method('findOneBy')->willReturn($user);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $this->entityManager->expects(self::never())->method('persist');

        $this->makeService($mailer)->requestReset('patient@medlink.test');
    }

    public function testConfirmResetThrowsBadRequestWhenTokenIsUnknown(): void
    {
        $this->passwordResetTokenRepository->method('findOneByTokenHash')->willReturn(null);

        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(BadRequestHttpException::class);

        $this->service->confirmReset('unknown-token', 'NewValidPass1');
    }

    public function testConfirmResetThrowsGoneWhenTokenIsAlreadyUsed(): void
    {
        $token = $this->makeToken(new \DateTimeImmutable('+1 hour'));
        $token->markUsed(new \DateTimeImmutable('-1 minute'));
        $this->passwordResetTokenRepository->method('findOneByTokenHash')->willReturn($token);

        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(GoneHttpException::class);

        $this->service->confirmReset('some-token', 'NewValidPass1');
    }

    public function testConfirmResetThrowsGoneWhenTokenIsExpired(): void
    {
        $token = $this->makeToken(new \DateTimeImmutable('-1 second'));
        $this->passwordResetTokenRepository->method('findOneByTokenHash')->willReturn($token);

        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(GoneHttpException::class);

        $this->service->confirmReset('some-token', 'NewValidPass1');
    }

    #[DataProvider('provideInvalidPasswords')]
    public function testConfirmResetRejectsAWeakNewPassword(string $weakPassword): void
    {
        $token = $this->makeToken(new \DateTimeImmutable('+1 hour'));
        $this->passwordResetTokenRepository->method('findOneByTokenHash')->willReturn($token);

        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(BadRequestHttpException::class);

        $this->service->confirmReset('some-token', $weakPassword);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidPasswords(): iterable
    {
        yield 'too short' => ['Ab1'];
        yield 'no digit' => ['NoDigitsHere'];
        yield 'no letter' => ['12345678'];
    }

    public function testConfirmResetSetsThePasswordMarksTheTokenUsedAndRevokesRefreshTokens(): void
    {
        $token = $this->makeToken(new \DateTimeImmutable('+1 hour'));
        $this->passwordResetTokenRepository->method('findOneByTokenHash')->willReturn($token);

        $refreshTokenRepository = $this->createMock(EntityRepository::class);
        $staleRefreshToken = $this->createStub(RefreshToken::class);
        $refreshTokenRepository->expects(self::once())->method('findBy')
            ->with(['username' => 'patient@medlink.test'])
            ->willReturn([$staleRefreshToken]);
        $this->entityManager->method('getRepository')->with(RefreshToken::class)->willReturn($refreshTokenRepository);

        $this->entityManager->expects(self::once())->method('remove')->with($staleRefreshToken);
        $this->entityManager->expects(self::once())->method('flush');

        $this->service->confirmReset('some-token', 'NewValidPass1');

        self::assertTrue($token->isUsed());
    }

    private function makeUser(int $id, string $email): User
    {
        $user = new User($email, 'Jeanne', 'Dupont');
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }

    private function makeToken(\DateTimeImmutable $expiresAt): PasswordResetToken
    {
        return new PasswordResetToken($this->makeUser(1, 'patient@medlink.test'), 'hashed-token', $expiresAt);
    }

    private function makeService(MailerInterface $mailer): PasswordResetService
    {
        return new PasswordResetService(
            $this->entityManager,
            $this->userRepository,
            $this->passwordResetTokenRepository,
            $this->passwordHasher,
            new PasswordResetMailer($mailer, 'http://localhost:5173', 'medlink', 'no-reply@medlink.app'),
        );
    }
}
