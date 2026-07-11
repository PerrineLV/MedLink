<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Message;
use App\Entity\User;
use App\Exception\InvalidMessageException;
use App\Security\Voter\MessageVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class MessageService
{
    private const CONTENT_MAX_LENGTH = 2000;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function send(User $recipient, string $content): Message
    {
        $this->assertValid($content);

        $sender = $this->security->getUser();
        if (!$sender instanceof User) {
            throw new AccessDeniedException('Utilisateur non authentifié.');
        }

        if (!$this->security->isGranted(MessageVoter::SEND, $recipient)) {
            throw new AccessDeniedException("Vous n'êtes pas autorisé à envoyer un message à cet utilisateur.");
        }

        $message = new Message($sender, $recipient, $content);

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $message;
    }

    public function markRead(Message $message): Message
    {
        $currentUser = $this->security->getUser();
        if (!$currentUser instanceof User) {
            throw new AccessDeniedException('Utilisateur non authentifié.');
        }

        if (!$this->security->isGranted(MessageVoter::MARK_READ, $message)) {
            throw new AccessDeniedException("Vous n'êtes pas autorisé à marquer ce message comme lu.");
        }

        $message->markRead();
        $this->entityManager->flush();

        return $message;
    }

    private function assertValid(string $content): void
    {
        if ('' === trim($content)) {
            throw new InvalidMessageException('Le message ne peut pas être vide.');
        }

        if (mb_strlen($content) > self::CONTENT_MAX_LENGTH) {
            throw new InvalidMessageException(sprintf('Le message ne peut pas dépasser %d caractères.', self::CONTENT_MAX_LENGTH));
        }
    }
}
