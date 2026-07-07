<?php

declare(strict_types=1);

namespace App\Dto;

final class TreatmentPatchInput
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $dosage = null,
        public readonly ?string $scheduledTime = null,
        public readonly ?bool $active = null,
    ) {
    }
}
