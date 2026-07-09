<?php

declare(strict_types=1);

namespace App\Dto;

final class AppointmentInput
{
    public function __construct(
        public readonly int $patientId,
        public readonly string $scheduledAt,
        public readonly ?string $notes = null,
    ) {
    }
}
