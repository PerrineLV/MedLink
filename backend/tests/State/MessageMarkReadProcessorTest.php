<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Patch;
use App\Entity\Message;
use App\Entity\User;
use App\Service\MessageService;
use App\State\MessageMarkReadProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class MessageMarkReadProcessorTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private Security&Stub $security;
    private MessageMarkReadProcessor $processor;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createStub(Security::class);

        $messageService = new MessageService($this->entityManager, $this->security);

        $this->processor = new MessageMarkReadProcessor($messageService);
    }

    public function testMarksTheMessageFromReadDataAsRead(): void
    {
        $patient = new User('patient@medlink.test', 'Jeanne', 'Dupont');
        $soignant = new User('soignant@medlink.test', 'Paul', 'Martin');
        $message = new Message($patient, $soignant, 'Bonjour docteur.');

        $this->security->method('getUser')->willReturn($soignant);
        $this->security->method('isGranted')->willReturn(true);
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->processor->process(null, new Patch(), [], ['read_data' => $message]);

        self::assertSame($message, $result);
        self::assertTrue($result->isRead());
    }

    public function testThrowsNotFoundWhenReadDataIsNotAMessage(): void
    {
        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(NotFoundHttpException::class);

        $this->processor->process(null, new Patch(), [], ['read_data' => null]);
    }
}
