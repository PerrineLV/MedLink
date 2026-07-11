<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\AccountController;
use App\Entity\User;
use App\Repository\JournalEntryRepository;
use App\Repository\PatientAidantRepository;
use App\Repository\PatientSoignantRepository;
use App\Repository\TreatmentRepository;
use App\Repository\UserRepository;
use App\Service\AccountService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AccountControllerTest extends TestCase
{
    private User $user;
    private Security&Stub $security;
    private UserPasswordHasherInterface&Stub $passwordHasher;
    private AccountController $controller;

    protected function setUp(): void
    {
        $this->user = new User('patient@medlink.test', 'Jeanne', 'Dupont');
        $this->user->setRoles([User::ROLE_PATIENT]);
        $this->user->setPassword('hashed-current-password');
        (new \ReflectionProperty(User::class, 'id'))->setValue($this->user, 1);

        $this->security = $this->createStub(Security::class);
        $this->security->method('getUser')->willReturn($this->user);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($this->createStub(EntityRepository::class));

        $this->passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed-new-password');

        $accountService = new AccountService(
            $entityManager,
            $this->passwordHasher,
            $this->createStub(JournalEntryRepository::class),
            $this->createStub(TreatmentRepository::class),
            $this->createStub(PatientAidantRepository::class),
            $this->createStub(PatientSoignantRepository::class),
            $this->createStub(UserRepository::class),
        );

        $this->controller = new AccountController($this->security, $accountService);
    }

    public function testMeReturnsCurrentUserData(): void
    {
        $response = $this->controller->me();

        $body = json_decode((string) $response->getContent(), true);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('patient@medlink.test', $body['email']);
        self::assertSame('Jeanne', $body['firstName']);
        self::assertSame([User::ROLE_PATIENT], $body['roles']);
        self::assertArrayNotHasKey('password', $body);
    }

    public function testMeThrowsAccessDeniedWhenNoUserIsAuthenticated(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $accountService = new AccountService(
            $entityManager,
            $this->createStub(UserPasswordHasherInterface::class),
            $this->createStub(JournalEntryRepository::class),
            $this->createStub(TreatmentRepository::class),
            $this->createStub(PatientAidantRepository::class),
            $this->createStub(PatientSoignantRepository::class),
            $this->createStub(UserRepository::class),
        );
        $controller = new AccountController($security, $accountService);

        $this->expectException(AccessDeniedHttpException::class);

        $controller->me();
    }

    public function testChangePasswordReturns200OnSuccess(): void
    {
        $this->passwordHasher->method('isPasswordValid')->willReturn(true);

        $response = $this->controller->changePassword($this->jsonRequest([
            'currentPassword' => 'CurrentPass1',
            'newPassword' => 'NewValidPass1',
        ]));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testChangePasswordReturns403WhenCurrentPasswordIsWrong(): void
    {
        $this->passwordHasher->method('isPasswordValid')->willReturn(false);

        $response = $this->controller->changePassword($this->jsonRequest([
            'currentPassword' => 'WrongPass1',
            'newPassword' => 'NewValidPass1',
        ]));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testChangePasswordReturns400WhenNewPasswordIsMissing(): void
    {
        $response = $this->controller->changePassword($this->jsonRequest([
            'currentPassword' => 'CurrentPass1',
        ]));

        self::assertSame(400, $response->getStatusCode());
    }

    public function testChangePasswordReturns400OnInvalidJsonBody(): void
    {
        $request = Request::create('/api/me/password', 'PATCH', server: ['CONTENT_TYPE' => 'application/json'], content: '{invalid');

        $response = $this->controller->changePassword($request);

        self::assertSame(400, $response->getStatusCode());
    }

    public function testChangeEmailReturns200OnSuccess(): void
    {
        $this->passwordHasher->method('isPasswordValid')->willReturn(true);

        $response = $this->controller->changeEmail($this->jsonRequest([
            'password' => 'CurrentPass1',
            'newEmail' => 'nouveau@medlink.test',
        ]));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testChangeEmailReturns403WhenPasswordIsWrong(): void
    {
        $this->passwordHasher->method('isPasswordValid')->willReturn(false);

        $response = $this->controller->changeEmail($this->jsonRequest([
            'password' => 'WrongPass1',
            'newEmail' => 'nouveau@medlink.test',
        ]));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testChangeEmailReturns400ForInvalidEmail(): void
    {
        $this->passwordHasher->method('isPasswordValid')->willReturn(true);

        $response = $this->controller->changeEmail($this->jsonRequest([
            'password' => 'CurrentPass1',
            'newEmail' => 'pas-un-email',
        ]));

        self::assertSame(400, $response->getStatusCode());
    }

    public function testChangeEmailReturns400WhenNewEmailIsMissing(): void
    {
        $response = $this->controller->changeEmail($this->jsonRequest([
            'password' => 'CurrentPass1',
        ]));

        self::assertSame(400, $response->getStatusCode());
    }

    public function testExportReturnsJsonResponseWithAttachmentHeader(): void
    {
        $response = $this->controller->export();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('medlink_export_1_', (string) $response->headers->get('Content-Disposition'));
        $body = json_decode((string) $response->getContent(), true);
        self::assertSame('patient@medlink.test', $body['account']['email']);
    }

    public function testDeleteReturns200OnSuccess(): void
    {
        $this->passwordHasher->method('isPasswordValid')->willReturn(true);

        $response = $this->controller->delete($this->jsonRequest(['password' => 'CurrentPass1']));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testDeleteReturns403WhenPasswordIsWrong(): void
    {
        $this->passwordHasher->method('isPasswordValid')->willReturn(false);

        $response = $this->controller->delete($this->jsonRequest(['password' => 'WrongPass1']));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testDeleteReturns400WhenPasswordIsMissing(): void
    {
        $response = $this->controller->delete($this->jsonRequest([]));

        self::assertSame(400, $response->getStatusCode());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonRequest(array $payload): Request
    {
        return Request::create(
            '/api/me',
            'PATCH',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );
    }
}
