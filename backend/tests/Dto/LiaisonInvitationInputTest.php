<?php

declare(strict_types=1);

namespace App\Tests\Dto;

use App\Dto\LiaisonInvitationInput;
use PHPUnit\Framework\TestCase;

final class LiaisonInvitationInputTest extends TestCase
{
    public function testConstructorSetsEmail(): void
    {
        $input = new LiaisonInvitationInput(email: 'aidant@medlink.test');

        self::assertSame('aidant@medlink.test', $input->email);
    }
}
