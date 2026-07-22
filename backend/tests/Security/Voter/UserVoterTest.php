<?php

declare(strict_types=1);

namespace App\Tests\Security\Voter;

use App\Entity\PatientAidant;
use App\Entity\PatientSoignant;
use App\Entity\User;
use App\Repository\PatientAidantRepository;
use App\Repository\PatientSoignantRepository;
use App\Security\Voter\UserVoter;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class UserVoterTest extends TestCase
{
    private PatientAidantRepository&Stub $patientAidantRepository;
    private PatientSoignantRepository&Stub $patientSoignantRepository;
    private UserVoter $voter;

    protected function setUp(): void
    {
        $this->patientAidantRepository = $this->createStub(PatientAidantRepository::class);
        $this->patientSoignantRepository = $this->createStub(PatientSoignantRepository::class);
        $this->voter = new UserVoter($this->patientAidantRepository, $this->patientSoignantRepository);
    }

    public function testPatientCanViewAndManageTheirOwnData(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $token = $this->tokenFor($patient);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $patient, [UserVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $patient, [UserVoter::MANAGE]));
    }

    public function testPatientCannotAccessAnotherPatientsData(): void
    {
        $patientA = $this->makeUser(1, User::ROLE_PATIENT);
        $patientB = $this->makeUser(2, User::ROLE_PATIENT);
        $token = $this->tokenFor($patientA);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $patientB, [UserVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $patientB, [UserVoter::MANAGE]));
    }

    public function testAidantCanViewAndManageAnActivelyAttachedPatient(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $aidant = $this->makeUser(2, User::ROLE_AIDANT);

        $this->patientAidantRepository
            ->method('hasActiveRelation')
            ->willReturn(true);

        $token = $this->tokenFor($aidant);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $patient, [UserVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $patient, [UserVoter::MANAGE]));
    }

    public function testAidantIsDeniedWhenNoRelationExists(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $aidant = $this->makeUser(2, User::ROLE_AIDANT);

        $this->patientAidantRepository->method('hasActiveRelation')->willReturn(false);

        $token = $this->tokenFor($aidant);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $patient, [UserVoter::VIEW]));
    }

    public function testAidantIsDeniedWhenTheRelationIsInactive(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $aidant = $this->makeUser(2, User::ROLE_AIDANT);

        $inactiveRelation = new PatientAidant($patient, $aidant);
        $inactiveRelation->setActive(false);

        // Mirrors what the real repository query does: filtering on active = true
        // excludes a deactivated relation, so it must resolve to false here too.
        $this->patientAidantRepository->method('hasActiveRelation')->willReturn($inactiveRelation->isActive());

        $token = $this->tokenFor($aidant);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $patient, [UserVoter::MANAGE]));
    }

    public function testSoignantCanOnlyViewAnActivelyReferredPatient(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);

        $this->patientSoignantRepository
            ->method('hasActiveRelation')
            ->willReturn(true);

        $token = $this->tokenFor($soignant);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $patient, [UserVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $patient, [UserVoter::MANAGE]));
    }

    public function testSoignantIsDeniedWhenTheRelationIsInactive(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);

        $inactiveRelation = new PatientSoignant($patient, $soignant);
        $inactiveRelation->setActive(false);

        $this->patientSoignantRepository->method('hasActiveRelation')->willReturn($inactiveRelation->isActive());

        $token = $this->tokenFor($soignant);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $patient, [UserVoter::VIEW]));
    }

    public function testVoterAbstainsWhenSubjectIsNotAPatient(): void
    {
        $soignant = $this->makeUser(1, User::ROLE_SOIGNANT);
        $token = $this->tokenFor($soignant);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($token, $soignant, [UserVoter::VIEW]));
    }

    public function testAnUnauthenticatedTokenIsDenied(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $patient, [UserVoter::VIEW]));
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
