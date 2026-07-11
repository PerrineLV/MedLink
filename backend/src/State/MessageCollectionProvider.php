<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<Message>
 */
final class MessageCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly UserRepository $userRepository,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return list<Message>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $currentUser = $this->security->getUser();
        if (!$currentUser instanceof User) {
            return [];
        }

        $conversationParam = $this->requestStack->getCurrentRequest()?->query->get('conversation');
        if (null === $conversationParam) {
            return [];
        }

        $otherUser = $this->userRepository->find((int) $conversationParam);
        if (!$otherUser instanceof User) {
            return [];
        }

        return $this->messageRepository->findConversation($currentUser, $otherUser);
    }
}
