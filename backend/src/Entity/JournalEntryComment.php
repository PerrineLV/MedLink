<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\JournalEntryCommentInput;
use App\Repository\JournalEntryCommentRepository;
use App\State\JournalEntryCommentProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: JournalEntryCommentRepository::class)]
#[ORM\Table(name: 'journal_entry_comment')]
#[ApiResource(
    operations: [
        new Post(processor: JournalEntryCommentProcessor::class, input: JournalEntryCommentInput::class),
    ],
    normalizationContext: ['groups' => ['journal_entry:read']],
)]
class JournalEntryComment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['journal_entry:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: JournalEntry::class, inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: false)]
    private JournalEntry $journalEntry;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $author;

    #[ORM\Column(length: 1000)]
    #[Groups(['journal_entry:read'])]
    private string $text;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['journal_entry:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct(JournalEntry $journalEntry, User $author, string $text)
    {
        $this->journalEntry = $journalEntry;
        $this->author = $author;
        $this->text = $text;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJournalEntry(): JournalEntry
    {
        return $this->journalEntry;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    #[Groups(['journal_entry:read'])]
    public function getAuthorId(): ?int
    {
        return $this->author->getId();
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
