<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Post;
use App\Dto\MessageInput;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\MessageService;
use App\State\MessageProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class MessageProcessorTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private UserRepository&MockObject $userRepository;
    private Security&Stub $security;
    private MessageProcessor $processor;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->security = $this->createStub(Security::class);

        $messageService = new MessageService($this->entityManager, $this->security);

        $this->processor = new MessageProcessor($messageService, $this->userRepository);
    }

    public function testSendsToTheResolvedRecipient(): void
    {
        $sender = new User('sender@medlink.test', 'Jeanne', 'Dupont');
        $recipient = new User('recipient@medlink.test', 'Paul', 'Martin');

        $this->userRepository->expects(self::once())->method('find')->with(2)->willReturn($recipient);
        $this->security->method('getUser')->willReturn($sender);
        $this->security->method('isGranted')->willReturn(true);
        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->processor->process(new MessageInput(recipientId: 2, content: 'Bonjour docteur.'), new Post());

        self::assertSame($sender, $result->getSender());
        self::assertSame($recipient, $result->getRecipient());
        self::assertSame('Bonjour docteur.', $result->getContent());
    }

    public function testThrowsNotFoundWhenRecipientDoesNotExist(): void
    {
        $this->userRepository->expects(self::once())->method('find')->willReturn(null);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(NotFoundHttpException::class);

        $this->processor->process(new MessageInput(recipientId: 999, content: 'Bonjour docteur.'), new Post());
    }
}
