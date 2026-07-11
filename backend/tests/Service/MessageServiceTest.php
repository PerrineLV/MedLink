<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Message;
use App\Entity\User;
use App\Exception\InvalidMessageException;
use App\Security\Voter\MessageVoter;
use App\Service\MessageService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class MessageServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private Security&Stub $security;
    private MessageService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createStub(Security::class);
        $this->service = new MessageService($this->entityManager, $this->security);
    }

    public function testSendPersistsForALinkedRecipient(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);

        $this->security->method('getUser')->willReturn($patient);
        $this->security->method('isGranted')->willReturnCallback(
            fn (string $attribute, mixed $subject): bool => MessageVoter::SEND === $attribute && $subject === $soignant,
        );

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $message = $this->service->send($soignant, 'Bonjour docteur.');

        self::assertSame($patient, $message->getSender());
        self::assertSame($soignant, $message->getRecipient());
        self::assertSame('Bonjour docteur.', $message->getContent());
        self::assertFalse($message->isRead());
    }

    public function testSendThrowsAccessDeniedWhenNoUserIsAuthenticated(): void
    {
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);

        $this->security->method('getUser')->willReturn(null);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(AccessDeniedException::class);

        $this->service->send($soignant, 'Bonjour');
    }

    public function testSendThrowsAccessDeniedWhenSenderAndRecipientAreNotLinked(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);

        $this->security->method('getUser')->willReturn($patient);
        $this->security->method('isGranted')->willReturn(false);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(AccessDeniedException::class);

        $this->service->send($soignant, 'Bonjour');
    }

    #[DataProvider('provideInvalidContent')]
    public function testSendRejectsInvalidContent(string $content): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);

        $this->security->method('getUser')->willReturn($patient);
        $this->security->method('isGranted')->willReturn(true);

        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(InvalidMessageException::class);

        $this->service->send($soignant, $content);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidContent(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'too long' => [str_repeat('a', 2001)];
    }

    public function testMarkReadMarksAMessageAsReadForItsRecipient(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $message = new Message($patient, $soignant, 'Bonjour docteur.');

        $this->security->method('getUser')->willReturn($soignant);
        $this->security->method('isGranted')->willReturnCallback(
            fn (string $attribute, mixed $subject): bool => MessageVoter::MARK_READ === $attribute && $subject === $message,
        );

        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->service->markRead($message);

        self::assertTrue($result->isRead());
    }

    public function testMarkReadThrowsAccessDeniedWhenNoUserIsAuthenticated(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $message = new Message($patient, $soignant, 'Bonjour docteur.');

        $this->security->method('getUser')->willReturn(null);

        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(AccessDeniedException::class);

        $this->service->markRead($message);
    }

    public function testMarkReadThrowsAccessDeniedForAUserOutsideTheConversation(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $soignant = $this->makeUser(2, User::ROLE_SOIGNANT);
        $anotherSoignant = $this->makeUser(3, User::ROLE_SOIGNANT);
        $message = new Message($patient, $soignant, 'Bonjour docteur.');

        $this->security->method('getUser')->willReturn($anotherSoignant);
        $this->security->method('isGranted')->willReturn(false);

        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(AccessDeniedException::class);

        $this->service->markRead($message);
    }

    private function makeUser(int $id, string $role): User
    {
        $user = new User(sprintf('user-%d@medlink.test', $id), 'Prenom', 'Nom');
        $user->setRoles([$role]);

        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }
}
