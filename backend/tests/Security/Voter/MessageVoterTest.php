<?php

declare(strict_types=1);

namespace App\Tests\Security\Voter;

use App\Entity\Message;
use App\Entity\User;
use App\Security\MessageableContact;
use App\Security\MessageableContacts;
use App\Security\Voter\MessageVoter;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class MessageVoterTest extends TestCase
{
    private MessageableContacts&Stub $messageableContacts;
    private MessageVoter $voter;

    protected function setUp(): void
    {
        $this->messageableContacts = $this->createStub(MessageableContacts::class);
        $this->voter = new MessageVoter($this->messageableContacts);
    }

    public function testAPatientCanSendToTheirActiveSoignant(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);

        $this->messageableContacts->method('forUser')->willReturnCallback(
            fn (User $user): array => $user === $patient ? [new MessageableContact($soignant, User::ROLE_SOIGNANT)] : [],
        );

        $token = $this->tokenFor($patient);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $soignant, [MessageVoter::SEND]));
    }

    public function testAnAidantCanSendToTheSoignantOfTheirSharedPatient(): void
    {
        $aidant = $this->makeUser(1, User::ROLE_AIDANT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);

        $this->messageableContacts->method('forUser')->willReturnCallback(
            fn (User $user): array => $user === $aidant ? [new MessageableContact($soignant, User::ROLE_SOIGNANT)] : [],
        );

        $token = $this->tokenFor($aidant);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $soignant, [MessageVoter::SEND]));
    }

    public function testAPatientCannotSendToAnAidant(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $aidant = $this->makeUser(2, User::ROLE_AIDANT);

        // Même rattachés, patient <-> aidant est explicitement interdit
        // (ML-70) : ils communiquent déjà en direct hors MedLink.
        $this->messageableContacts->method('forUser')->willReturn([]);

        $token = $this->tokenFor($patient);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $aidant, [MessageVoter::SEND]));
    }

    public function testAnAidantCannotSendToAnotherAidant(): void
    {
        $aidant = $this->makeUser(1, User::ROLE_AIDANT);
        $anotherAidant = $this->makeUser(2, User::ROLE_AIDANT);

        $this->messageableContacts->method('forUser')->willReturn([]);

        $token = $this->tokenFor($aidant);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $anotherAidant, [MessageVoter::SEND]));
    }

    public function testASoignantCannotSendToAnotherSoignant(): void
    {
        $soignant = $this->makeUser(1, User::ROLE_SOIGNANT);
        $anotherSoignant = $this->makeUser(2, User::ROLE_SOIGNANT);

        $this->messageableContacts->method('forUser')->willReturn([]);

        $token = $this->tokenFor($soignant);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $anotherSoignant, [MessageVoter::SEND]));
    }

    public function testTheRecipientCanMarkAMessageAsRead(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $message = new Message($patient, $soignant, 'Bonjour docteur.');

        $token = $this->tokenFor($soignant);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $message, [MessageVoter::MARK_READ]));
    }

    public function testTheSenderCannotMarkTheirOwnMessageAsRead(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $message = new Message($patient, $soignant, 'Bonjour docteur.');

        $token = $this->tokenFor($patient);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $message, [MessageVoter::MARK_READ]));
    }

    public function testAThirdPartyCannotMarkAMessageThatIsNotTheirsAsRead(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $anotherSoignant = $this->makeUser(3, User::ROLE_SOIGNANT);
        $message = new Message($patient, $soignant, 'Bonjour docteur.');

        $token = $this->tokenFor($anotherSoignant);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $message, [MessageVoter::MARK_READ]));
    }

    public function testVoterAbstainsForAnUnsupportedSubject(): void
    {
        $user = $this->makeUser(1, User::ROLE_SOIGNANT);
        $token = $this->tokenFor($user);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($token, $user, [MessageVoter::MARK_READ]));
    }

    private function makeUser(int $id, string $role): User
    {
        $user = new User(sprintf('user-%d@medlink.test', $id), 'Prenom', 'Nom');
        $user->setRoles([$role]);

        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }

    private function tokenFor(User $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
