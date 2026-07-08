<?php

declare(strict_types=1);

namespace App\Dto;

final class TreatmentPatchInput
{
    /**
     * @param list<array<string, mixed>>|null $schedules
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $dosage = null,
        public readonly ?array $schedules = null,
        public readonly ?bool $active = null,
    ) {
    }
}
