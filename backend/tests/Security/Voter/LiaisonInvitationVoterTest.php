<?php

declare(strict_types=1);

namespace App\Tests\Security\Voter;

use App\Entity\PatientAidant;
use App\Entity\PatientSoignant;
use App\Entity\User;
use App\Security\Voter\LiaisonInvitationVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class LiaisonInvitationVoterTest extends TestCase
{
    private LiaisonInvitationVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new LiaisonInvitationVoter();
    }

    public function testTheInvitedAidantCanRespondToTheInvitation(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $aidant = $this->makeUser(2, User::ROLE_AIDANT);
        $relation = new PatientAidant($patient, $aidant);

        $token = $this->tokenFor($aidant);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $relation, [LiaisonInvitationVoter::RESPOND]));
    }

    public function testTheInvitedSoignantCanRespondToTheInvitation(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $relation = new PatientSoignant($patient, $soignant);

        $token = $this->tokenFor($soignant);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $relation, [LiaisonInvitationVoter::RESPOND]));
    }

    public function testThePatientWhoSentTheInvitationCannotRespondToItThemselves(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $aidant = $this->makeUser(2, User::ROLE_AIDANT);
        $relation = new PatientAidant($patient, $aidant);

        $token = $this->tokenFor($patient);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $relation, [LiaisonInvitationVoter::RESPOND]));
    }

    public function testAThirdPartyCannotRespondToAnInvitationThatIsNotTheirs(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $anotherSoignant = $this->makeUser(3, User::ROLE_SOIGNANT);
        $relation = new PatientSoignant($patient, $soignant);

        $token = $this->tokenFor($anotherSoignant);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $relation, [LiaisonInvitationVoter::RESPOND]));
    }

    public function testVoterAbstainsForAnUnrelatedSubject(): void
    {
        $user = $this->makeUser(1, User::ROLE_SOIGNANT);
        $token = $this->tokenFor($user);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($token, $user, [LiaisonInvitationVoter::RESPOND]));
    }

    public function testThePatientCanRevokeTheirOwnAidantLink(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $aidant = $this->makeUser(2, User::ROLE_AIDANT);
        $relation = new PatientAidant($patient, $aidant);

        $token = $this->tokenFor($patient);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $relation, [LiaisonInvitationVoter::REVOKE]));
    }

    public function testThePatientCanRevokeTheirOwnSoignantLink(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $relation = new PatientSoignant($patient, $soignant);

        $token = $this->tokenFor($patient);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $relation, [LiaisonInvitationVoter::REVOKE]));
    }

    public function testTheInvitedAidantCannotRevokeTheirOwnLink(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $aidant = $this->makeUser(2, User::ROLE_AIDANT);
        $relation = new PatientAidant($patient, $aidant);

        $token = $this->tokenFor($aidant);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $relation, [LiaisonInvitationVoter::REVOKE]));
    }

    public function testAThirdPartyCannotRevokeALinkThatIsNotTheirs(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $anotherPatient = $this->makeUser(3, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $relation = new PatientSoignant($patient, $soignant);

        $token = $this->tokenFor($anotherPatient);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $relation, [LiaisonInvitationVoter::REVOKE]));
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
