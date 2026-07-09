<?php

declare(strict_types=1);

namespace App\Dto;

final class MessageInput
{
    public function __construct(
        public readonly int $recipientId,
        public readonly string $content,
    ) {
    }
}
