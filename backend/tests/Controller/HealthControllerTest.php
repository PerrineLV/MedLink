<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\HealthController;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class HealthControllerTest extends TestCase
{
    private Connection&Stub $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createStub(Connection::class);
    }

    public function testReturnsOkStatusAsJsonWhenTheDatabaseIsReachable(): void
    {
        $controller = new HealthController($this->connection, new NullLogger());

        $response = $controller->__invoke();

        self::assertSame(200, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString('{"status":"ok","database":"ok"}', $response->getContent());
    }

    public function testReturns503WithoutInfrastructureDetailsWhenTheDatabaseIsUnreachable(): void
    {
        $this->connection->method('executeQuery')->willThrowException(
            $this->createStub(DbalException::class),
        );
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $controller = new HealthController($this->connection, $logger);

        $response = $controller->__invoke();

        self::assertSame(503, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString('{"status":"error","database":"error"}', $response->getContent());
    }
}
