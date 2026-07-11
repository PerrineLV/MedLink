<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Controls access to the admin endpoints (ML-53). Unlike UserVoter, there is
 * no patient relation to check here: the ticket's scope is a flat
 * ROLE_ADMIN gate on the whole /api/admin surface, not a per-resource
 * relation, so a role check alone is legitimate.
 *
 * @extends Voter<'ADMIN_MANAGE_USERS', null>
 */
final class AdminVoter extends Voter
{
    public const MANAGE_USERS = 'ADMIN_MANAGE_USERS';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::MANAGE_USERS === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        return $user instanceof User && in_array(User::ROLE_ADMIN, $user->getRoles(), true);
    }
}
