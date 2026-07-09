<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\PatientAidant;
use App\Entity\PatientSoignant;
use App\Entity\User;
use App\Repository\PatientAidantRepository;
use App\Repository\PatientSoignantRepository;
use App\Security\Voter\UserVoter;
use App\Service\LiaisonInvitationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class LiaisonInvitationServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private PatientAidantRepository&Stub $patientAidantRepository;
    private PatientSoignantRepository&Stub $patientSoignantRepository;
    private Security&Stub $security;
    private LiaisonInvitationService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->patientAidantRepository = $this->createStub(PatientAidantRepository::class);
        $this->patientSoignantRepository = $this->createStub(PatientSoignantRepository::class);
        $this->security = $this->createStub(Security::class);
        $this->service = new LiaisonInvitationService(
            $this->entityManager,
            $this->patientAidantRepository,
            $this->patientSoignantRepository,
            $this->security,
        );
    }

    public function testInviteCreatesAPendingPatientAidantLink(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $aidant = $this->makeUser(2, User::ROLE_AIDANT);

        $this->security->method('getUser')->willReturn($patient);
        $this->security->method('isGranted')->willReturnCallback(
            fn (string $attribute, mixed $subject): bool => UserVoter::MANAGE === $attribute && $subject === $patient,
        );
        $this->patientAidantRepository->method('findOneBy')->willReturn(null);

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $invitation = $this->service->invite($aidant);

        self::assertSame(1, $invitation->patientId);
        self::assertSame(2, $invitation->inviteeId);
        self::assertSame(User::ROLE_AIDANT, $invitation->inviteeRole);
        self::assertFalse($invitation->active);
    }

    public function testInviteCreatesAPendingPatientSoignantLink(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);

        $this->security->method('getUser')->willReturn($patient);
        $this->security->method('isGranted')->willReturnCallback(
            fn (string $attribute, mixed $subject): bool => UserVoter::MANAGE === $attribute && $subject === $patient,
        );
        $this->patientSoignantRepository->method('findOneBy')->willReturn(null);

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $invitation = $this->service->invite($soignant);

        self::assertSame(1, $invitation->patientId);
        self::assertSame(2, $invitation->inviteeId);
        self::assertSame(User::ROLE_SOIGNANT, $invitation->inviteeRole);
        self::assertFalse($invitation->active);
    }

    public function testInviteThrowsConflictWhenAnAidantLinkAlreadyExists(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $aidant = $this->makeUser(2, User::ROLE_AIDANT);

        $this->security->method('getUser')->willReturn($patient);
        $this->security->method('isGranted')->willReturn(true);
        $this->patientAidantRepository->method('findOneBy')->willReturn(new PatientAidant($patient, $aidant));

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(ConflictHttpException::class);

        $this->service->invite($aidant);
    }

    public function testInviteThrowsConflictWhenASoignantLinkAlreadyExists(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);

        $this->security->method('getUser')->willReturn($patient);
        $this->security->method('isGranted')->willReturn(true);
        $this->patientSoignantRepository->method('findOneBy')->willReturn(new PatientSoignant($patient, $soignant));

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(ConflictHttpException::class);

        $this->service->invite($soignant);
    }

    public function testInviteThrowsAccessDeniedWhenNoUserIsAuthenticated(): void
    {
        $aidant = $this->makeUser(2, User::ROLE_AIDANT);

        $this->security->method('getUser')->willReturn(null);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(AccessDeniedException::class);

        $this->service->invite($aidant);
    }

    public function testInviteThrowsAccessDeniedWhenTheCallerIsAnAidant(): void
    {
        $aidant = $this->makeUser(1, User::ROLE_AIDANT);
        $invitee = $this->makeUser(2, User::ROLE_SOIGNANT);

        $this->security->method('getUser')->willReturn($aidant);
        $this->security->method('isGranted')->willReturn(false);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(AccessDeniedException::class);

        $this->service->invite($invitee);
    }

    public function testInviteThrowsAccessDeniedWhenTheCallerIsASoignant(): void
    {
        $soignant = $this->makeUser(1, User::ROLE_SOIGNANT);
        $invitee = $this->makeUser(2, User::ROLE_AIDANT);

        $this->security->method('getUser')->willReturn($soignant);
        $this->security->method('isGranted')->willReturn(false);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(AccessDeniedException::class);

        $this->service->invite($invitee);
    }

    public function testAcceptActivatesAPendingAidantLink(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $aidant = $this->makeUser(2, User::ROLE_AIDANT);
        $relation = $this->makeAidantRelation($patient, $aidant, 5);

        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $invitation = $this->service->accept($relation);

        self::assertTrue($relation->isActive());
        self::assertSame('aidant-5', $invitation->id);
        self::assertTrue($invitation->active);
    }

    public function testAcceptActivatesAPendingSoignantLink(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $relation = $this->makeSoignantRelation($patient, $soignant, 5);

        $this->entityManager->expects(self::once())->method('flush');

        $invitation = $this->service->accept($relation);

        self::assertTrue($relation->isActive());
        self::assertSame('soignant-5', $invitation->id);
        self::assertTrue($invitation->active);
    }

    public function testAcceptThrowsConflictWhenTheLinkIsAlreadyActive(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $aidant = $this->makeUser(2, User::ROLE_AIDANT);
        $relation = $this->makeAidantRelation($patient, $aidant, 5);
        $relation->setActive(true);

        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(ConflictHttpException::class);

        $this->service->accept($relation);
    }

    public function testRejectRemovesAPendingAidantLink(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $aidant = $this->makeUser(2, User::ROLE_AIDANT);
        $relation = $this->makeAidantRelation($patient, $aidant, 5);

        $this->entityManager->expects(self::once())->method('remove')->with($relation);
        $this->entityManager->expects(self::once())->method('flush');

        $invitation = $this->service->reject($relation);

        self::assertSame('aidant-5', $invitation->id);
        self::assertFalse($invitation->active);
    }

    public function testRejectRemovesAPendingSoignantLink(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $relation = $this->makeSoignantRelation($patient, $soignant, 5);

        $this->entityManager->expects(self::once())->method('remove')->with($relation);
        $this->entityManager->expects(self::once())->method('flush');

        $invitation = $this->service->reject($relation);

        self::assertSame('soignant-5', $invitation->id);
        self::assertFalse($invitation->active);
    }

    public function testRejectThrowsConflictWhenTheLinkIsAlreadyActive(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $relation = $this->makeSoignantRelation($patient, $soignant, 5);
        $relation->setActive(true);

        $this->entityManager->expects(self::never())->method('remove');
        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(ConflictHttpException::class);

        $this->service->reject($relation);
    }

    private function makeUser(int $id, string $role): User
    {
        $user = new User(sprintf('user-%d@medlink.test', $id), 'Prenom', 'Nom');
        $user->setRoles([$role]);

        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }

    private function makeAidantRelation(User $patient, User $aidant, int $id): PatientAidant
    {
        $relation = new PatientAidant($patient, $aidant);
        $relation->setActive(false);

        (new \ReflectionProperty(PatientAidant::class, 'id'))->setValue($relation, $id);

        return $relation;
    }

    private function makeSoignantRelation(User $patient, User $soignant, int $id): PatientSoignant
    {
        $relation = new PatientSoignant($patient, $soignant);
        $relation->setActive(false);

        (new \ReflectionProperty(PatientSoignant::class, 'id'))->setValue($relation, $id);

        return $relation;
    }
}
