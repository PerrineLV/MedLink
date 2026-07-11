<?php

declare(strict_types=1);

namespace App\Dto;

final class TreatmentInput
{
    /**
     * @param list<array<string, mixed>> $schedules
     */
    public function __construct(
        public readonly int $patientId,
        public readonly string $name,
        public readonly string $dosage,
        public readonly array $schedules,
    ) {
    }
}
