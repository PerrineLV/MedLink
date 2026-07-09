<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\MessageInput;
use App\Repository\MessageRepository;
use App\State\MessageCollectionProvider;
use App\State\MessageMarkReadProcessor;
use App\State\MessageProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'message')]
#[ApiResource(
    operations: [
        new GetCollection(provider: MessageCollectionProvider::class),
        new Post(processor: MessageProcessor::class, input: MessageInput::class),
        new Patch(
            uriTemplate: '/messages/{id}/read',
            deserialize: false,
            processor: MessageMarkReadProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['message:read']],
)]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['message:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $sender;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $recipient;

    #[ORM\Column(length: 2000)]
    #[Groups(['message:read'])]
    private string $content;

    #[ORM\Column(name: 'is_read')]
    #[Groups(['message:read'])]
    private bool $read = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['message:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $sender, User $recipient, string $content)
    {
        $this->sender = $sender;
        $this->recipient = $recipient;
        $this->content = $content;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSender(): User
    {
        return $this->sender;
    }

    public function getRecipient(): User
    {
        return $this->recipient;
    }

    #[Groups(['message:read'])]
    public function getSenderId(): ?int
    {
        return $this->sender->getId();
    }

    #[Groups(['message:read'])]
    public function getRecipientId(): ?int
    {
        return $this->recipient->getId();
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function isRead(): bool
    {
        return $this->read;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function markRead(): static
    {
        $this->read = true;

        return $this;
    }
}
