<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\AdminSupervisionController;
use App\Repository\FailedLoginAttemptRepository;
use App\Repository\UserRepository;
use App\Service\AdminSupervisionService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class AdminSupervisionControllerTest extends TestCase
{
    private Connection&Stub $connection;
    private FailedLoginAttemptRepository&Stub $failedLoginAttemptRepository;
    private UserRepository&Stub $userRepository;

    protected function setUp(): void
    {
        $this->connection = $this->createStub(Connection::class);
        $this->failedLoginAttemptRepository = $this->createStub(FailedLoginAttemptRepository::class);
        $this->userRepository = $this->createStub(UserRepository::class);
    }

    public function testHealthSummaryReturnsTheSummaryForAnAdmin(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(true);

        $this->failedLoginAttemptRepository->method('countSince')->willReturn(2);
        $this->userRepository->method('countActiveByRole')->willReturn(1);

        $service = new AdminSupervisionService(
            $this->connection,
            $this->failedLoginAttemptRepository,
            $this->userRepository,
        );
        $controller = new AdminSupervisionController($security, $service);

        $response = $controller->healthSummary();
        $body = json_decode((string) $response->getContent(), true);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ok', $body['health']['status']);
        self::assertSame(2, $body['failedLoginAttempts']['last24h']);
        self::assertSame(['patient' => 1, 'aidant' => 1, 'soignant' => 1], $body['activeAccountsByRole']);
    }

    public function testHealthSummaryThrowsAccessDeniedForNonAdmin(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(false);

        $service = new AdminSupervisionService(
            $this->connection,
            $this->failedLoginAttemptRepository,
            $this->userRepository,
        );
        $controller = new AdminSupervisionController($security, $service);

        $this->expectException(AccessDeniedHttpException::class);

        $controller->healthSummary();
    }
}
