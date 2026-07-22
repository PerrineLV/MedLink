<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\JournalEntry;
use App\Entity\JournalEntryComment;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class JournalEntryCommentTest extends TestCase
{
    private function createJournalEntry(): JournalEntry
    {
        $patient = new User('patient@medlink.test', 'Jeanne', 'Dupont');

        return new JournalEntry($patient, $patient, 3, 4, '12/8');
    }

    public function testConstructorSetsBaseFields(): void
    {
        $journalEntry = $this->createJournalEntry();
        $author = new User('soignant@medlink.test', 'Paul', 'Martin');

        $comment = new JournalEntryComment($journalEntry, $author, 'Tout va bien.');

        self::assertSame($journalEntry, $comment->getJournalEntry());
        self::assertSame($author, $comment->getAuthor());
        self::assertSame('Tout va bien.', $comment->getText());
        self::assertNull($comment->getId());
        self::assertNull($comment->getAuthorId());
    }

    public function testGetCreatedAtIsSetAtConstruction(): void
    {
        $before = new \DateTimeImmutable();

        $comment = new JournalEntryComment($this->createJournalEntry(), new User('soignant@medlink.test', 'Paul', 'Martin'), 'Tout va bien.');

        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $comment->getCreatedAt());
        self::assertLessThanOrEqual($after, $comment->getCreatedAt());
    }
}
