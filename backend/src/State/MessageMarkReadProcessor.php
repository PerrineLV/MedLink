<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Message;
use App\Service\MessageService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<null, Message>
 */
final class MessageMarkReadProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly MessageService $messageService,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Message
    {
        $message = $context['read_data'] ?? null;
        if (!$message instanceof Message) {
            throw new NotFoundHttpException('Message introuvable.');
        }

        return $this->messageService->markRead($message);
    }
}
