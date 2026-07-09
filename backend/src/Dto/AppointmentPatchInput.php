<?php

declare(strict_types=1);

namespace App\Dto;

final class AppointmentPatchInput
{
    public function __construct(
        public readonly ?string $scheduledAt = null,
        public readonly ?string $status = null,
        public readonly ?string $notes = null,
    ) {
    }
}
