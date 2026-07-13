<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\PasswordResetController;
use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use App\Service\PasswordResetMailer;
use App\Service\PasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class PasswordResetControllerTest extends TestCase
{
    private UserRepository&Stub $userRepository;
    private PasswordResetTokenRepository&Stub $passwordResetTokenRepository;
    private MailerInterface&Stub $mailer;
    private PasswordResetController $controller;

    protected function setUp(): void
    {
        $this->userRepository = $this->createStub(UserRepository::class);
        $this->passwordResetTokenRepository = $this->createStub(PasswordResetTokenRepository::class);
        $this->mailer = $this->createStub(MailerInterface::class);

        $this->controller = new PasswordResetController(
            $this->makeService(),
            $this->makeLimiter(),
        );
    }

    public function testRequestReturns200WithGenericMessageWhenTheAccountExists(): void
    {
        $this->userRepository->method('findOneBy')->willReturn($this->makeUser(1, 'patient@medlink.test'));

        $response = $this->controller->request($this->jsonRequest('/api/password-reset/request', ['email' => 'patient@medlink.test']));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testRequestReturnsTheSame200GenericMessageWhenTheAccountDoesNotExist(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);

        $existing = $this->controller->request($this->jsonRequest('/api/password-reset/request', ['email' => 'patient@medlink.test']));
        $unknown = $this->controller->request($this->jsonRequest('/api/password-reset/request', ['email' => 'inconnu@medlink.test']));

        self::assertSame(200, $unknown->getStatusCode());
        self::assertSame($existing->getContent(), $unknown->getContent());
    }

    public function testRequestReturns400WhenEmailIsMissing(): void
    {
        $response = $this->controller->request($this->jsonRequest('/api/password-reset/request', []));

        self::assertSame(400, $response->getStatusCode());
    }

    public function testRequestReturns200WhenPlatformIsMobile(): void
    {
        $this->userRepository->method('findOneBy')->willReturn($this->makeUser(1, 'patient@medlink.test'));

        $response = $this->controller->request($this->jsonRequest('/api/password-reset/request', [
            'email' => 'patient@medlink.test',
            'platform' => 'mobile',
        ]));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testRequestReturns400WhenPlatformIsInvalid(): void
    {
        $response = $this->controller->request($this->jsonRequest('/api/password-reset/request', [
            'email' => 'patient@medlink.test',
            'platform' => 'desktop',
        ]));

        self::assertSame(400, $response->getStatusCode());
    }

    public function testRequestReturns429AfterRateLimitExceeded(): void
    {
        $controller = new PasswordResetController($this->makeService(), $this->makeLimiter(limit: 2));
        $payload = ['email' => 'patient@medlink.test'];

        $controller->request($this->jsonRequest('/api/password-reset/request', $payload));
        $controller->request($this->jsonRequest('/api/password-reset/request', $payload));
        $response = $controller->request($this->jsonRequest('/api/password-reset/request', $payload));

        self::assertSame(429, $response->getStatusCode());
    }

    public function testConfirmReturns200OnSuccess(): void
    {
        $token = new PasswordResetToken($this->makeUser(1, 'patient@medlink.test'), 'hashed', new \DateTimeImmutable('+1 hour'));
        $this->passwordResetTokenRepository->method('findOneByTokenHash')->willReturn($token);

        $response = $this->controller->confirm($this->jsonRequest('/api/password-reset/confirm', [
            'token' => 'le-token',
            'newPassword' => 'NewValidPass1',
        ]));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testConfirmReturns410WhenTokenIsExpired(): void
    {
        $token = new PasswordResetToken($this->makeUser(1, 'patient@medlink.test'), 'hashed', new \DateTimeImmutable('-1 second'));
        $this->passwordResetTokenRepository->method('findOneByTokenHash')->willReturn($token);

        $response = $this->controller->confirm($this->jsonRequest('/api/password-reset/confirm', [
            'token' => 'le-token',
            'newPassword' => 'NewValidPass1',
        ]));

        self::assertSame(410, $response->getStatusCode());
    }

    public function testConfirmReturns400WhenTokenIsUnknown(): void
    {
        $this->passwordResetTokenRepository->method('findOneByTokenHash')->willReturn(null);

        $response = $this->controller->confirm($this->jsonRequest('/api/password-reset/confirm', [
            'token' => 'le-token',
            'newPassword' => 'NewValidPass1',
        ]));

        self::assertSame(400, $response->getStatusCode());
    }

    public function testConfirmReturns400OnInvalidJsonBody(): void
    {
        $request = Request::create('/api/password-reset/confirm', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{invalid');

        $response = $this->controller->confirm($request);

        self::assertSame(400, $response->getStatusCode());
    }

    private function makeUser(int $id, string $email): User
    {
        $user = new User($email, 'Jeanne', 'Dupont');
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonRequest(string $uri, array $payload): Request
    {
        return Request::create(
            $uri,
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );
    }

    private function makeService(): PasswordResetService
    {
        $refreshTokenRepository = $this->createStub(EntityRepository::class);
        $refreshTokenRepository->method('findBy')->willReturn([]);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($refreshTokenRepository);

        $passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn('hashed-new-password');

        return new PasswordResetService(
            $entityManager,
            $this->userRepository,
            $this->passwordResetTokenRepository,
            $passwordHasher,
            new PasswordResetMailer($this->mailer, 'http://localhost:5173', 'medlink', 'no-reply@medlink.app'),
        );
    }

    private function makeLimiter(int $limit = 5): RateLimiterFactory
    {
        return new RateLimiterFactory(
            ['id' => 'password_reset_request_by_ip', 'policy' => 'fixed_window', 'limit' => $limit, 'interval' => '1 minute'],
            new InMemoryStorage(),
        );
    }
}
