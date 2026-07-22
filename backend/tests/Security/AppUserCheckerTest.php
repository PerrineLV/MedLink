<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\AppUserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\DisabledException;

final class AppUserCheckerTest extends TestCase
{
    private AppUserChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new AppUserChecker();
    }

    public function testActiveUserPassesPreAuthCheck(): void
    {
        $user = new User('patient@medlink.test', 'Jeanne', 'Dupont');

        $this->checker->checkPreAuth($user);

        $this->addToAssertionCount(1);
    }

    public function testDisabledUserIsRejectedAtPreAuth(): void
    {
        $user = new User('patient@medlink.test', 'Jeanne', 'Dupont');
        $user->setActive(false);

        $this->expectException(DisabledException::class);

        $this->checker->checkPreAuth($user);
    }

    public function testCheckPostAuthIsANoOp(): void
    {
        $user = new User('patient@medlink.test', 'Jeanne', 'Dupont');

        $this->checker->checkPostAuth($user);

        $this->addToAssertionCount(1);
    }
}
