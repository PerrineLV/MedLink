<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\AdminController;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AdminUserService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class AdminControllerTest extends TestCase
{
    private Security&Stub $security;
    private UserRepository&Stub $userRepository;
    private AdminController $controller;

    protected function setUp(): void
    {
        $this->security = $this->createStub(Security::class);
        $this->security->method('isGranted')->willReturn(true);

        $this->userRepository = $this->createStub(UserRepository::class);
        $entityManager = $this->createStub(EntityManagerInterface::class);

        $adminUserService = new AdminUserService($this->userRepository, $entityManager);

        $this->controller = new AdminController($this->security, $adminUserService);
    }

    public function testListUsersReturnsPaginatedResults(): void
    {
        $patient = $this->makeUser(1, 'patient@medlink.test', User::ROLE_PATIENT);
        $this->userRepository->method('search')->willReturn(['items' => [$patient], 'total' => 1]);

        $response = $this->controller->listUsers(Request::create('/api/admin/users?role=patient&status=actif'));

        $body = json_decode((string) $response->getContent(), true);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $body['total']);
        self::assertSame('patient@medlink.test', $body['items'][0]['email']);
        self::assertArrayNotHasKey('password', $body['items'][0]);
    }

    public function testListUsersReturns400OnInvalidRoleFilter(): void
    {
        $response = $this->controller->listUsers(Request::create('/api/admin/users?role=superadmin'));

        self::assertSame(400, $response->getStatusCode());
    }

    public function testListUsersThrowsAccessDeniedForNonAdmin(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(false);
        $adminUserService = new AdminUserService($this->userRepository, $this->createStub(EntityManagerInterface::class));
        $controller = new AdminController($security, $adminUserService);

        $this->expectException(AccessDeniedHttpException::class);

        $controller->listUsers(Request::create('/api/admin/users'));
    }

    public function testUpdateStatusDeactivatesAnAccount(): void
    {
        $patient = $this->makeUser(1, 'patient@medlink.test', User::ROLE_PATIENT);
        $this->userRepository->method('find')->willReturn($patient);

        $response = $this->controller->updateStatus(1, $this->jsonRequest(['active' => false]));

        $body = json_decode((string) $response->getContent(), true);
        self::assertSame(200, $response->getStatusCode());
        self::assertFalse($body['active']);
    }

    public function testUpdateStatusReturns404WhenUserDoesNotExist(): void
    {
        $this->userRepository->method('find')->willReturn(null);

        $response = $this->controller->updateStatus(999, $this->jsonRequest(['active' => false]));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testUpdateStatusReturns400OnInvalidJsonBody(): void
    {
        $request = Request::create(
            '/api/admin/users/1/status',
            'PATCH',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{invalid',
        );

        $response = $this->controller->updateStatus(1, $request);

        self::assertSame(400, $response->getStatusCode());
    }

    public function testUpdateStatusReturns400WhenActiveFieldIsMissing(): void
    {
        $response = $this->controller->updateStatus(1, $this->jsonRequest([]));

        self::assertSame(400, $response->getStatusCode());
    }

    public function testUpdateStatusReturns400WhenActiveFieldIsNotABoolean(): void
    {
        $response = $this->controller->updateStatus(1, $this->jsonRequest(['active' => 'yes']));

        self::assertSame(400, $response->getStatusCode());
    }

    public function testUpdateStatusThrowsAccessDeniedForNonAdmin(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(false);
        $adminUserService = new AdminUserService($this->userRepository, $this->createStub(EntityManagerInterface::class));
        $controller = new AdminController($security, $adminUserService);

        $this->expectException(AccessDeniedHttpException::class);

        $controller->updateStatus(1, $this->jsonRequest(['active' => false]));
    }

    private function makeUser(int $id, string $email, string $role): User
    {
        $user = new User($email, 'Prenom', 'Nom');
        $user->setRoles([$role]);

        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonRequest(array $payload): Request
    {
        return Request::create(
            '/api/admin/users/1/status',
            'PATCH',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );
    }
}
