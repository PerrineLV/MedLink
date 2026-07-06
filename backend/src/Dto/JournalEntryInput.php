<?php

declare(strict_types=1);

namespace App\Dto;

final class JournalEntryInput
{
    public function __construct(
        public readonly int $patientId,
        public readonly int $mood,
        public readonly int $painLevel,
        public readonly string $bloodPressure,
        public readonly ?string $note = null,
    ) {
    }
}
