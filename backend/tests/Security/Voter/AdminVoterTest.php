<?php

declare(strict_types=1);

namespace App\Tests\Security\Voter;

use App\Entity\User;
use App\Security\Voter\AdminVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class AdminVoterTest extends TestCase
{
    private AdminVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new AdminVoter();
    }

    public function testAdminIsGrantedAccess(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN);
        $token = $this->tokenFor($admin);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, null, [AdminVoter::MANAGE_USERS]),
        );
    }

    public function testAdminIsGrantedAccessToSupervision(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN);
        $token = $this->tokenFor($admin);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, null, [AdminVoter::VIEW_SUPERVISION]),
        );
    }

    #[DataProvider('nonAdminRoleProvider')]
    public function testNonAdminIsDeniedAccess(string $role): void
    {
        $user = $this->makeUser($role);
        $token = $this->tokenFor($user);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, null, [AdminVoter::MANAGE_USERS]),
        );
    }

    #[DataProvider('nonAdminRoleProvider')]
    public function testNonAdminIsDeniedAccessToSupervision(string $role): void
    {
        $user = $this->makeUser($role);
        $token = $this->tokenFor($user);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, null, [AdminVoter::VIEW_SUPERVISION]),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonAdminRoleProvider(): iterable
    {
        yield 'patient' => [User::ROLE_PATIENT];
        yield 'aidant' => [User::ROLE_AIDANT];
        yield 'soignant' => [User::ROLE_SOIGNANT];
    }

    public function testVoterAbstainsOnUnrelatedAttribute(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN);
        $token = $this->tokenFor($admin);

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote($token, null, ['SOME_OTHER_ATTRIBUTE']),
        );
    }

    private function makeUser(string $role): User
    {
        $user = new User('user@medlink.test', 'Prenom', 'Nom');
        $user->setRoles([$role]);

        return $user;
    }

    private function tokenFor(User $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
