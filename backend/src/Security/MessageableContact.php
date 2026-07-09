<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;

/**
 * Un contact autorisé (ML-70), avec le rôle sous lequel il est contacté et,
 * pour une paire aidant/soignant uniquement, le ou les patients communs qui
 * justifient l'autorisation — utile à afficher quand l'un des deux a
 * plusieurs patients et donc plusieurs contacts de ce type (ex. un soignant
 * avec plusieurs aidants ne sait pas lequel correspond à quel patient sans
 * cette info).
 */
final class MessageableContact
{
    /**
     * @param list<User> $viaPatients
     */
    public function __construct(
        public readonly User $user,
        public readonly string $role,
        public readonly array $viaPatients = [],
    ) {
    }
}
