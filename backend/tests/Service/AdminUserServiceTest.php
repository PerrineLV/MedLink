<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AdminUserService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AdminUserServiceTest extends TestCase
{
    private UserRepository&Stub $userRepository;
    private EntityManagerInterface&Stub $entityManager;
    private AdminUserService $service;

    protected function setUp(): void
    {
        $this->userRepository = $this->createStub(UserRepository::class);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->service = new AdminUserService($this->userRepository, $this->entityManager);
    }

    public function testListUsersPassesResolvedFiltersToTheRepository(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())
            ->method('search')
            ->with(User::ROLE_SOIGNANT, true, 2, 10)
            ->willReturn(['items' => [], 'total' => 0]);
        $service = new AdminUserService($userRepository, $this->entityManager);

        $result = $service->listUsers('soignant', 'actif', 2, 10);

        self::assertSame(['items' => [], 'total' => 0], $result);
    }

    public function testListUsersWithoutFiltersPassesNullToTheRepository(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())
            ->method('search')
            ->with(null, null, 1, 20)
            ->willReturn(['items' => [], 'total' => 0]);
        $service = new AdminUserService($userRepository, $this->entityManager);

        $result = $service->listUsers(null, null, 1, 20);

        self::assertSame(['items' => [], 'total' => 0], $result);
    }

    public function testListUsersRejectsAnInvalidRoleFilter(): void
    {
        $this->expectException(BadRequestHttpException::class);

        $this->service->listUsers('superadmin', null, 1, 20);
    }

    public function testListUsersRejectsAnInvalidStatusFilter(): void
    {
        $this->expectException(BadRequestHttpException::class);

        $this->service->listUsers(null, 'archivé', 1, 20);
    }

    public function testSetUserActiveUpdatesAndReturnsTheUser(): void
    {
        $user = new User('patient@medlink.test', 'Jeanne', 'Dupont');
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, 1);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('find')->with(1)->willReturn($user);
        $service = new AdminUserService($userRepository, $this->entityManager);

        $result = $service->setUserActive(1, false);

        self::assertFalse($result->isActive());
    }

    public function testSetUserActiveThrowsWhenUserDoesNotExist(): void
    {
        $this->userRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->service->setUserActive(999, false);
    }
}
