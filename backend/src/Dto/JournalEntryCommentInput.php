<?php

declare(strict_types=1);

namespace App\Dto;

final class JournalEntryCommentInput
{
    public function __construct(
        public readonly int $journalEntryId,
        public readonly string $text,
    ) {
    }
}
