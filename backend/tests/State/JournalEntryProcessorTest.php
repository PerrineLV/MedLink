<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Post;
use App\Dto\JournalEntryInput;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\JournalEntryService;
use App\State\JournalEntryProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class JournalEntryProcessorTest extends TestCase
{
    private UserRepository&MockObject $userRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private Security&Stub $security;
    private JournalEntryProcessor $processor;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createStub(Security::class);

        $journalEntryService = new JournalEntryService($this->entityManager, $this->security);

        $this->processor = new JournalEntryProcessor($journalEntryService, $this->userRepository);
    }

    public function testThrowsAccessDeniedWithoutLookingUpAPatientWhenPatientIdIsNull(): void
    {
        $aidant = $this->makeUser(2, User::ROLE_AIDANT);
        $this->security->method('getUser')->willReturn($aidant);

        $this->userRepository->expects(self::never())->method('find');
        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(AccessDeniedException::class);

        $this->processor->process(
            new JournalEntryInput(null, 3, 4, '120/80', null),
            new Post(),
        );
    }

    public function testThrowsNotFoundWhenPatientIdDoesNotMatchAnyUser(): void
    {
        $aidant = $this->makeUser(2, User::ROLE_AIDANT);
        $this->security->method('getUser')->willReturn($aidant);
        $this->userRepository->method('find')->with(99)->willReturn(null);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(NotFoundHttpException::class);

        $this->processor->process(
            new JournalEntryInput(99, 3, 4, '120/80', null),
            new Post(),
        );
    }

    public function testThrowsAccessDeniedWhenTheAidantHasNoActiveRelationToTheGivenPatient(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $aidant = $this->makeUser(2, User::ROLE_AIDANT);
        $this->security->method('getUser')->willReturn($aidant);
        $this->security->method('isGranted')->willReturn(false);
        $this->userRepository->method('find')->with(1)->willReturn($patient);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(AccessDeniedException::class);

        $this->processor->process(
            new JournalEntryInput(1, 3, 4, '120/80', null),
            new Post(),
        );
    }

    public function testCreatesEntryWhenTheAidantIsActivelyAttachedToThePatient(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $aidant = $this->makeUser(2, User::ROLE_AIDANT);
        $this->security->method('getUser')->willReturn($aidant);
        $this->security->method('isGranted')->willReturnCallback(
            fn (string $attribute, mixed $subject): bool => $subject === $patient,
        );
        $this->userRepository->method('find')->with(1)->willReturn($patient);

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $entry = $this->processor->process(
            new JournalEntryInput(1, 3, 4, '120/80', 'Ça va mieux'),
            new Post(),
        );

        self::assertSame($patient, $entry->getPatient());
        self::assertSame($aidant, $entry->getAuthor());
    }

    private function makeUser(int $id, string $role): User
    {
        $user = new User(sprintf('user-%d@medlink.test', $id), 'Prenom', 'Nom');
        $user->setRoles([$role]);

        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }
}
