<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Exception\InvalidJournalEntryException;
use App\Security\Voter\UserVoter;
use App\Service\JournalEntryService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class JournalEntryServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private Security&Stub $security;
    private JournalEntryService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createStub(Security::class);
        $this->service = new JournalEntryService($this->entityManager, $this->security);
    }

    public function testCreateEntryPersistsWhenThePatientEntersItThemselves(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);

        $this->security->method('getUser')->willReturn($patient);
        $this->security->method('isGranted')->willReturnCallback(
            fn (string $attribute, mixed $subject): bool => UserVoter::MANAGE === $attribute && $subject === $patient,
        );

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $entry = $this->service->create($patient, 3, 4, '120/80', 'Ça va mieux');

        self::assertSame($patient, $entry->getPatient());
        self::assertSame($patient, $entry->getAuthor());
        self::assertSame(3, $entry->getMood());
        self::assertSame(4, $entry->getPainLevel());
        self::assertSame('120/80', $entry->getBloodPressure());
        self::assertSame('Ça va mieux', $entry->getNote());
        self::assertFalse($entry->isEnteredByCaregiver());
    }

    public function testCreateEntryPersistsForAnActivelyAttachedAidant(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $aidant = $this->makeUser(2, User::ROLE_AIDANT);

        $this->security->method('getUser')->willReturn($aidant);
        $this->security->method('isGranted')->willReturnCallback(
            fn (string $attribute, mixed $subject): bool => UserVoter::MANAGE === $attribute && $subject === $patient,
        );

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $entry = $this->service->create($patient, 2, 0, '110/70', null);

        self::assertSame($patient, $entry->getPatient());
        self::assertSame($aidant, $entry->getAuthor());
        self::assertNull($entry->getNote());
        self::assertTrue($entry->isEnteredByCaregiver());
    }

    public function testCreateThrowsAccessDeniedWhenNoUserIsAuthenticated(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);

        $this->security->method('getUser')->willReturn(null);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(AccessDeniedException::class);

        $this->service->create($patient, 3, 4, '120/80', null);
    }

    public function testCreateThrowsAccessDeniedWhenTheAidantHasNoActiveRelation(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $aidant = $this->makeUser(2, User::ROLE_AIDANT);

        $this->security->method('getUser')->willReturn($aidant);
        $this->security->method('isGranted')->willReturn(false);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(AccessDeniedException::class);

        $this->service->create($patient, 3, 4, '120/80', null);
    }

    #[DataProvider('provideInvalidEntryData')]
    public function testCreateRejectsInvalidData(
        int $mood,
        int $painLevel,
        string $bloodPressure,
        ?string $note,
    ): void {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);

        $this->security->method('getUser')->willReturn($patient);
        $this->security->method('isGranted')->willReturn(true);

        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(InvalidJournalEntryException::class);

        $this->service->create($patient, $mood, $painLevel, $bloodPressure, $note);
    }

    /**
     * @return iterable<string, array{int, int, string, ?string}>
     */
    public static function provideInvalidEntryData(): iterable
    {
        yield 'mood too low' => [0, 4, '120/80', null];
        yield 'mood too high' => [6, 4, '120/80', null];
        yield 'pain level too low' => [3, -1, '120/80', null];
        yield 'pain level too high' => [3, 11, '120/80', null];
        yield 'blood pressure without slash' => [3, 4, 'abc', null];
        yield 'blood pressure with wrong separator' => [3, 4, '120-80', null];
        yield 'note too long' => [3, 4, '120/80', str_repeat('a', 1001)];
    }

    private function makeUser(int $id, string $role): User
    {
        $user = new User(sprintf('user-%d@medlink.test', $id), 'Prenom', 'Nom');
        $user->setRoles([$role]);

        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }
}
