<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class PasswordResetTokenTest extends TestCase
{
    public function testConstructorSetsBaseFields(): void
    {
        $user = new User('patient@medlink.test', 'Jeanne', 'Dupont');
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $token = new PasswordResetToken($user, 'hashed-token', $expiresAt);

        self::assertSame($user, $token->getUser());
        self::assertSame('hashed-token', $token->getTokenHash());
        self::assertSame($expiresAt, $token->getExpiresAt());
        self::assertNull($token->getUsedAt());
        self::assertFalse($token->isUsed());
        self::assertNull($token->getId());
    }

    public function testGetCreatedAtIsSetAtConstruction(): void
    {
        $before = new \DateTimeImmutable();

        $token = new PasswordResetToken(
            new User('patient@medlink.test', 'Jeanne', 'Dupont'),
            'hashed-token',
            new \DateTimeImmutable('+1 hour'),
        );

        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $token->getCreatedAt());
        self::assertLessThanOrEqual($after, $token->getCreatedAt());
    }

    public function testIsExpiredIsFalseBeforeExpiry(): void
    {
        $token = new PasswordResetToken(
            new User('patient@medlink.test', 'Jeanne', 'Dupont'),
            'hashed-token',
            new \DateTimeImmutable('+1 hour'),
        );

        self::assertFalse($token->isExpired(new \DateTimeImmutable()));
    }

    public function testIsExpiredIsTrueAfterExpiry(): void
    {
        $token = new PasswordResetToken(
            new User('patient@medlink.test', 'Jeanne', 'Dupont'),
            'hashed-token',
            new \DateTimeImmutable('-1 second'),
        );

        self::assertTrue($token->isExpired(new \DateTimeImmutable()));
    }

    public function testMarkUsedSetsUsedAt(): void
    {
        $token = new PasswordResetToken(
            new User('patient@medlink.test', 'Jeanne', 'Dupont'),
            'hashed-token',
            new \DateTimeImmutable('+1 hour'),
        );
        $usedAt = new \DateTimeImmutable();

        $token->markUsed($usedAt);

        self::assertTrue($token->isUsed());
        self::assertSame($usedAt, $token->getUsedAt());
    }
}
