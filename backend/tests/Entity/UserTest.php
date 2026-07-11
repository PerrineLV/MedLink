<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testConstructorSetsBaseFields(): void
    {
        $user = new User('patient@medlink.test', 'Jeanne', 'Dupont');

        self::assertSame('patient@medlink.test', $user->getEmail());
        self::assertSame('patient@medlink.test', $user->getUserIdentifier());
        self::assertSame('Jeanne', $user->getFirstName());
        self::assertSame('Dupont', $user->getLastName());
        self::assertEqualsWithDelta(
            (new \DateTimeImmutable())->getTimestamp(),
            $user->getCreatedAt()->getTimestamp(),
            1,
        );
    }

    public function testEmailIsMutable(): void
    {
        $user = new User('old@medlink.test', 'Jeanne', 'Dupont');

        $user->setEmail('new@medlink.test');

        self::assertSame('new@medlink.test', $user->getEmail());
        self::assertSame('new@medlink.test', $user->getUserIdentifier());
    }

    public function testPasswordIsMutable(): void
    {
        $user = new User('patient@medlink.test', 'Jeanne', 'Dupont');

        $user->setPassword('hashed-password');

        self::assertSame('hashed-password', $user->getPassword());
    }

    public function testNamesAreMutable(): void
    {
        $user = new User('patient@medlink.test', 'Jeanne', 'Dupont');

        $user->setFirstName('Marie');
        $user->setLastName('Martin');

        self::assertSame('Marie', $user->getFirstName());
        self::assertSame('Martin', $user->getLastName());
    }

    /**
     * @param list<string> $roles
     * @param list<string> $expected
     */
    #[DataProvider('provideRoles')]
    public function testRolesAreDeduplicated(array $roles, array $expected): void
    {
        $user = new User('soignant@medlink.test', 'Jeanne', 'Dupont');

        $user->setRoles($roles);

        self::assertSame($expected, $user->getRoles());
    }

    /**
     * @return iterable<string, array{list<string>, list<string>}>
     */
    public static function provideRoles(): iterable
    {
        yield 'single role' => [[User::ROLE_PATIENT], [User::ROLE_PATIENT]];
        yield 'multiple distinct roles' => [
            [User::ROLE_SOIGNANT, User::ROLE_ADMIN],
            [User::ROLE_SOIGNANT, User::ROLE_ADMIN],
        ];
        yield 'duplicated roles are deduplicated' => [
            [User::ROLE_AIDANT, User::ROLE_AIDANT],
            [User::ROLE_AIDANT],
        ];
        yield 'no roles' => [[], []];
    }

    public function testEraseCredentialsDoesNotAlterState(): void
    {
        $user = new User('patient@medlink.test', 'Jeanne', 'Dupont');
        $user->setPassword('hashed-password');

        $user->eraseCredentials();

        self::assertSame('hashed-password', $user->getPassword());
    }

    public function testTitleIsMutable(): void
    {
        $user = new User('soignant@medlink.test', 'Jeanne', 'Dupont');

        self::assertNull($user->getTitle());

        $user->setTitle('Dr');

        self::assertSame('Dr', $user->getTitle());
    }

    public function testConsentAtIsMutable(): void
    {
        $user = new User('patient@medlink.test', 'Jeanne', 'Dupont');

        self::assertNull($user->getConsentAt());

        $consentAt = new \DateTimeImmutable('-1 minute');
        $user->setConsentAt($consentAt);

        self::assertSame($consentAt, $user->getConsentAt());
    }

    public function testDeletedAtIsMutable(): void
    {
        $user = new User('patient@medlink.test', 'Jeanne', 'Dupont');

        self::assertNull($user->getDeletedAt());

        $deletedAt = new \DateTimeImmutable('-1 minute');
        $user->setDeletedAt($deletedAt);

        self::assertSame($deletedAt, $user->getDeletedAt());
    }
}
