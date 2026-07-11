<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\FailedLoginAttemptRepository;
use App\Repository\UserRepository;
use App\Service\AdminSupervisionService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class AdminSupervisionServiceTest extends TestCase
{
    private Connection&Stub $connection;
    private FailedLoginAttemptRepository&Stub $failedLoginAttemptRepository;
    private UserRepository&Stub $userRepository;
    private AdminSupervisionService $service;

    protected function setUp(): void
    {
        $this->connection = $this->createStub(Connection::class);
        $this->failedLoginAttemptRepository = $this->createStub(FailedLoginAttemptRepository::class);
        $this->userRepository = $this->createStub(UserRepository::class);

        $this->service = new AdminSupervisionService(
            $this->connection,
            $this->failedLoginAttemptRepository,
            $this->userRepository,
        );
    }

    public function testGetHealthSummaryReturnsOkWhenTheDatabaseIsReachable(): void
    {
        $this->failedLoginAttemptRepository->method('countSince')->willReturn(3);
        $this->userRepository->method('countActiveByRole')->willReturnMap([
            [User::ROLE_PATIENT, 4],
            [User::ROLE_AIDANT, 2],
            [User::ROLE_SOIGNANT, 1],
        ]);

        $summary = $this->service->getHealthSummary();

        self::assertSame('ok', $summary['health']['status']);
        self::assertSame(3, $summary['failedLoginAttempts']['last24h']);
        self::assertSame(
            ['patient' => 4, 'aidant' => 2, 'soignant' => 1],
            $summary['activeAccountsByRole'],
        );
    }

    public function testGetHealthSummaryReturnsDownWhenTheDatabaseQueryFails(): void
    {
        $this->connection->method('executeQuery')->willThrowException(
            $this->createStub(DbalException::class),
        );
        $this->failedLoginAttemptRepository->method('countSince')->willReturn(0);
        $this->userRepository->method('countActiveByRole')->willReturn(0);

        $summary = $this->service->getHealthSummary();

        self::assertSame('down', $summary['health']['status']);
    }

    public function testGetHealthSummaryNeverExposesNominativeData(): void
    {
        $this->failedLoginAttemptRepository->method('countSince')->willReturn(0);
        $this->userRepository->method('countActiveByRole')->willReturn(0);

        $summary = $this->service->getHealthSummary();
        $encoded = json_encode($summary, \JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('@', $encoded);
        self::assertStringNotContainsString('email', strtolower($encoded));
    }
}
