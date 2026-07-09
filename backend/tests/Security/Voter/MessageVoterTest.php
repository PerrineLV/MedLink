<?php

declare(strict_types=1);

namespace App\Tests\Security\Voter;

use App\Entity\Message;
use App\Entity\User;
use App\Repository\PatientAidantRepository;
use App\Repository\PatientSoignantRepository;
use App\Security\Voter\MessageVoter;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class MessageVoterTest extends TestCase
{
    private PatientAidantRepository&Stub $patientAidantRepository;
    private PatientSoignantRepository&Stub $patientSoignantRepository;
    private MessageVoter $voter;

    protected function setUp(): void
    {
        $this->patientAidantRepository = $this->createStub(PatientAidantRepository::class);
        $this->patientSoignantRepository = $this->createStub(PatientSoignantRepository::class);
        $this->voter = new MessageVoter($this->patientAidantRepository, $this->patientSoignantRepository);
    }

    public function testAPatientCanSendToTheirActivelyAttachedSoignant(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);

        $this->patientSoignantRepository->method('hasActiveRelation')->willReturn(true);

        $token = $this->tokenFor($patient);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $soignant, [MessageVoter::SEND]));
    }

    public function testASoignantCanReplyToTheirActivelyReferredPatient(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);

        $this->patientSoignantRepository->method('hasActiveRelation')->willReturn(true);

        $token = $this->tokenFor($soignant);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $patient, [MessageVoter::SEND]));
    }

    public function testAPatientCanSendToTheirActivelyAttachedAidant(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $aidant = $this->makeUser(2, User::ROLE_AIDANT);

        $this->patientAidantRepository->method('hasActiveRelation')->willReturn(true);

        $token = $this->tokenFor($patient);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $aidant, [MessageVoter::SEND]));
    }

    public function testAPatientCannotSendToASoignantWhoIsNotTheirs(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $anotherSoignant = $this->makeUser(2, User::ROLE_SOIGNANT);

        $this->patientSoignantRepository->method('hasActiveRelation')->willReturn(false);
        $this->patientAidantRepository->method('hasActiveRelation')->willReturn(false);

        $token = $this->tokenFor($patient);

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
