<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\RegistrationController;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\RegistrationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class RegistrationControllerTest extends TestCase
{
    private RegistrationController $controller;

    protected function setUp(): void
    {
        $this->controller = new RegistrationController(
            $this->makeRegistrationService(existingEmail: false),
            $this->makeLimiter(),
        );
    }

    public function testReturns201WithUserDataOnSuccess(): void
    {
        $response = ($this->controller)($this->jsonRequest($this->validPayload()));

        self::assertSame(201, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        self::assertSame('patient@medlink.test', $body['email']);
        self::assertSame('Jeanne', $body['firstName']);
        self::assertSame('Dupont', $body['lastName']);
        self::assertSame([User::ROLE_PATIENT], $body['roles']);
        self::assertNull($body['title']);
        self::assertArrayNotHasKey('password', $body);
    }

    public function testReturns400OnInvalidJsonBody(): void
    {
        $request = Request::create('/api/auth/register', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{invalid');

        $response = ($this->controller)($request);

        self::assertSame(400, $response->getStatusCode());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideRequiredFields(): iterable
    {
        yield 'email' => ['email'];
        yield 'password' => ['password'];
        yield 'firstName' => ['firstName'];
        yield 'lastName' => ['lastName'];
        yield 'role' => ['role'];
        yield 'consent' => ['consent'];
    }

    #[DataProvider('provideRequiredFields')]
    public function testReturns400WhenARequiredFieldIsMissing(string $missingField): void
    {
        $payload = $this->validPayload();
        unset($payload[$missingField]);

        $response = ($this->controller)($this->jsonRequest($payload));

        self::assertSame(400, $response->getStatusCode());
    }

    public function testReturns429AfterRateLimitExceeded(): void
    {
        $controller = new RegistrationController(
            $this->makeRegistrationService(existingEmail: false),
            $this->makeLimiter(limit: 2),
        );

        $controller($this->jsonRequest($this->validPayload()));
        $controller($this->jsonRequest($this->validPayload()));
        $response = $controller($this->jsonRequest($this->validPayload()));

        self::assertSame(429, $response->getStatusCode());
    }

    public function testConvertsServiceExceptionToJsonResponseWithMessage(): void
    {
        $controller = new RegistrationController(
            $this->makeRegistrationService(existingEmail: true),
            $this->makeLimiter(),
        );

        $response = $controller($this->jsonRequest($this->validPayload()));

        self::assertSame(409, $response->getStatusCode());
        self::assertSame(
            ['message' => 'Cet email est déjà utilisé.'],
            json_decode((string) $response->getContent(), true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'email' => 'patient@medlink.test',
            'password' => 'ValidPass1',
            'firstName' => 'Jeanne',
            'lastName' => 'Dupont',
            'role' => 'patient',
            'consent' => true,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonRequest(array $payload): Request
    {
        return Request::create(
            '/api/auth/register',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );
    }

    private function makeRegistrationService(bool $existingEmail): RegistrationService
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn(
            $existingEmail ? new User('patient@medlink.test', 'Existant', 'Existant') : null,
        );
        $passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn('hashed-password');

        return new RegistrationService($entityManager, $userRepository, $passwordHasher);
    }

    private function makeLimiter(int $limit = 5): RateLimiterFactory
    {
        return new RateLimiterFactory(
            ['id' => 'register_by_ip', 'policy' => 'fixed_window', 'limit' => $limit, 'interval' => '1 hour'],
            new InMemoryStorage(),
        );
    }
}
