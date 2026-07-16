<?php

declare(strict_types=1);

namespace App\Tests\ApiResource;

use App\ApiResource\MessageContact;
use App\Entity\User;
use App\Security\MessageableContact;
use PHPUnit\Framework\TestCase;

final class MessageContactTest extends TestCase
{
    public function testFromMessageableContactPropagatesTheUserTitle(): void
    {
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $soignant->setTitle('Dr');
        $contact = new MessageableContact($soignant, User::ROLE_SOIGNANT);

        $messageContact = MessageContact::fromMessageableContact($contact);

        self::assertSame('Dr', $messageContact->title);
    }

    public function testFromMessageableContactHasNullTitleWhenNoneIsSet(): void
    {
        $aidant = $this->makeUser(3, User::ROLE_AIDANT);
        $contact = new MessageableContact($aidant, User::ROLE_AIDANT);

        $messageContact = MessageContact::fromMessageableContact($contact);

        self::assertNull($messageContact->title);
    }

    private function makeUser(int $id, string $role): User
    {
        $user = new User(sprintf('user-%d@medlink.test', $id), 'Prenom', 'Nom');
        $user->setRoles([$role]);

        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }
}
