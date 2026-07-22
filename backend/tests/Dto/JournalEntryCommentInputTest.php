<?php

declare(strict_types=1);

namespace App\Tests\Dto;

use App\Dto\JournalEntryCommentInput;
use PHPUnit\Framework\TestCase;

final class JournalEntryCommentInputTest extends TestCase
{
    public function testConstructorSetsAllFields(): void
    {
        $input = new JournalEntryCommentInput(journalEntryId: 1, text: 'Tout va bien.');

        self::assertSame(1, $input->journalEntryId);
        self::assertSame('Tout va bien.', $input->text);
    }
}
