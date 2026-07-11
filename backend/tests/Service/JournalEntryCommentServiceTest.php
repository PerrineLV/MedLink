<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\JournalEntry;
use App\Entity\User;
use App\Exception\InvalidJournalEntryCommentException;
use App\Repository\PatientSoignantRepository;
use App\Service\JournalEntryCommentService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class JournalEntryCommentServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private PatientSoignantRepository&Stub $patientSoignantRepository;
    private Security&Stub $security;
    private JournalEntryCommentService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->patientSoignantRepository = $this->createStub(PatientSoignantRepository::class);
        $this->security = $this->createStub(Security::class);
        $this->service = new JournalEntryCommentService(
            $this->entityManager,
            $this->patientSoignantRepository,
            $this->security,
        );
    }

    public function testCreatePersistsForAnActivelyAttachedSoignant(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $entry = new JournalEntry($patient, $patient, 3, 4, '120/80');

        $this->security->method('getUser')->willReturn($soignant);
        $this->patientSoignantRepository->method('hasActiveRelation')->willReturn(true);

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $comment = $this->service->create($entry, 'À surveiller de près.');

        self::assertSame($entry, $comment->getJournalEntry());
        self::assertSame($soignant, $comment->getAuthor());
        self::assertSame('À surveiller de près.', $comment->getText());
    }

    public function testCreateThrowsAccessDeniedWhenNoUserIsAuthenticated(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $entry = new JournalEntry($patient, $patient, 3, 4, '120/80');

        $this->security->method('getUser')->willReturn(null);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(AccessDeniedException::class);

        $this->service->create($entry, 'Commentaire');
    }

    public function testCreateThrowsAccessDeniedForThePatientThemselves(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $entry = new JournalEntry($patient, $patient, 3, 4, '120/80');

        $this->security->method('getUser')->willReturn($patient);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(AccessDeniedException::class);

        $this->service->create($entry, 'Commentaire');
    }

    public function testCreateThrowsAccessDeniedForAnAidant(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $aidant = $this->makeUser(2, User::ROLE_AIDANT);
        $entry = new JournalEntry($patient, $patient, 3, 4, '120/80');

        $this->security->method('getUser')->willReturn($aidant);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(AccessDeniedException::class);

        $this->service->create($entry, 'Commentaire');
    }

    public function testCreateThrowsAccessDeniedWhenTheSoignantHasNoActiveRelation(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $entry = new JournalEntry($patient, $patient, 3, 4, '120/80');

        $this->security->method('getUser')->willReturn($soignant);
        $this->patientSoignantRepository->method('hasActiveRelation')->willReturn(false);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(AccessDeniedException::class);

        $this->service->create($entry, 'Commentaire');
    }

    public function testCreateRejectsAnEmptyComment(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $entry = new JournalEntry($patient, $patient, 3, 4, '120/80');

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(InvalidJournalEntryCommentException::class);

        $this->service->create($entry, '   ');
    }

    public function testCreateRejectsACommentThatIsTooLong(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $entry = new JournalEntry($patient, $patient, 3, 4, '120/80');

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(InvalidJournalEntryCommentException::class);

        $this->service->create($entry, str_repeat('a', 1001));
    }

    private function makeUser(int $id, string $role): User
    {
        $user = new User(sprintf('user-%d@medlink.test', $id), 'Prenom', 'Nom');
        $user->setRoles([$role]);

        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }
}
