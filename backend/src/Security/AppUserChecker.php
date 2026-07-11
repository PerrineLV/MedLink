<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Blocks a disabled account (App\Entity\User::$active) from obtaining a new
 * JWT at login (ML-53). A token already issued before deactivation stays
 * valid until it expires: this is JWT stateless, there is no blacklist.
 */
final class AppUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && !$user->isActive()) {
            throw new DisabledException('Ce compte a été désactivé.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
