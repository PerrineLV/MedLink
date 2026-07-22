<?php

declare(strict_types=1);

namespace App\Tests\Dto;

use App\Dto\MessageInput;
use PHPUnit\Framework\TestCase;

final class MessageInputTest extends TestCase
{
    public function testConstructorSetsAllFields(): void
    {
        $input = new MessageInput(recipientId: 2, content: 'Bonjour docteur.');

        self::assertSame(2, $input->recipientId);
        self::assertSame('Bonjour docteur.', $input->content);
    }
}
