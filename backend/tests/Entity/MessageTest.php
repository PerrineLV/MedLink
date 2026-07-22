<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Message;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class MessageTest extends TestCase
{
    private function createUser(string $email): User
    {
        return new User($email, 'Jeanne', 'Dupont');
    }

    public function testConstructorSetsBaseFieldsAndDefaultsToUnread(): void
    {
        $sender = $this->createUser('sender@medlink.test');
        $recipient = $this->createUser('recipient@medlink.test');

        $message = new Message($sender, $recipient, 'Bonjour, comment allez-vous ?');

        self::assertSame($sender, $message->getSender());
        self::assertSame($recipient, $message->getRecipient());
        self::assertSame('Bonjour, comment allez-vous ?', $message->getContent());
        self::assertFalse($message->isRead());
        self::assertNull($message->getId());
        self::assertNull($message->getSenderId());
        self::assertNull($message->getRecipientId());
    }

    public function testMarkReadSetsReadToTrue(): void
    {
        $message = new Message($this->createUser('sender@medlink.test'), $this->createUser('recipient@medlink.test'), 'Bonjour');

        $message->markRead();

        self::assertTrue($message->isRead());
    }

    public function testGetCreatedAtIsSetAtConstruction(): void
    {
        $before = new \DateTimeImmutable();

        $message = new Message($this->createUser('sender@medlink.test'), $this->createUser('recipient@medlink.test'), 'Bonjour');

        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $message->getCreatedAt());
        self::assertLessThanOrEqual($after, $message->getCreatedAt());
    }
}
