<?php

declare(strict_types=1);

namespace App\Dto;

final class LiaisonInvitationInput
{
    public function __construct(
        public readonly string $email,
    ) {
    }
}
