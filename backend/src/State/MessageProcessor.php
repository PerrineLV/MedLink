<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\MessageInput;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\MessageService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<MessageInput, Message>
 */
final class MessageProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly MessageService $messageService,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Message
    {
        $recipient = $this->userRepository->find($data->recipientId);
        if (!$recipient instanceof User) {
            throw new NotFoundHttpException('Destinataire introuvable.');
        }

        return $this->messageService->send($recipient, $data->content);
    }
}
