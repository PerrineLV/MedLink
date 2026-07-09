<?php

declare(strict_types=1);

namespace App\ApiResource;

use App\Entity\User;

/**
 * Patient commun via lequel un contact aidant/soignant est autorisé
 * (MessageContact::$viaPatients, ML-70).
 */
final class MessageContactPatient
{
    public function __construct(
        public readonly int $id,
        public readonly string $firstName,
        public readonly string $lastName,
    ) {
    }

    public static function fromUser(User $patient): self
    {
        return new self((int) $patient->getId(), $patient->getFirstName(), $patient->getLastName());
    }
}
