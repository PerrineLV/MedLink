<?php

declare(strict_types=1);

namespace App\Dto;

final class RegistrationInput
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $role,
        public readonly ?string $title,
        public readonly bool $consent,
    ) {
    }
}
