<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\FailedLoginAttempt;
use PHPUnit\Framework\TestCase;

final class FailedLoginAttemptTest extends TestCase
{
    public function testConstructorSetsCreatedAt(): void
    {
        $before = new \DateTimeImmutable();

        $attempt = new FailedLoginAttempt();

        $after = new \DateTimeImmutable();

        self::assertNull($attempt->getId());
        self::assertGreaterThanOrEqual($before, $attempt->getCreatedAt());
        self::assertLessThanOrEqual($after, $attempt->getCreatedAt());
    }
}
