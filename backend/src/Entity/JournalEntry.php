<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Dto\JournalEntryInput;
use App\Repository\JournalEntryRepository;
use App\State\JournalEntryCollectionProvider;
use App\State\JournalEntryProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: JournalEntryRepository::class)]
#[ORM\Table(name: 'journal_entry')]
#[ApiResource(
    operations: [
        new GetCollection(provider: JournalEntryCollectionProvider::class),
        new Get(security: "is_granted('PATIENT_VIEW', object.getPatient())"),
        new Post(processor: JournalEntryProcessor::class, input: JournalEntryInput::class),
    ],
    normalizationContext: ['groups' => ['journal_entry:read']],
)]
class JournalEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['journal_entry:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $patient;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $author;

    #[ORM\Column]
    #[Groups(['journal_entry:read'])]
    private int $mood;

    #[ORM\Column]
    #[Groups(['journal_entry:read'])]
    private int $painLevel;

    #[ORM\Column(length: 20)]
    #[Groups(['journal_entry:read'])]
    private string $bloodPressure;

    #[ORM\Column(length: 1000, nullable: true)]
    #[Groups(['journal_entry:read'])]
    private ?string $note;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['journal_entry:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        User $patient,
        User $author,
        int $mood,
        int $painLevel,
        string $bloodPressure,
        ?string $note = null,
    ) {
        $this->patient = $patient;
        $this->author = $author;
        $this->mood = $mood;
        $this->painLevel = $painLevel;
        $this->bloodPressure = $bloodPressure;
        $this->note = $note;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPatient(): User
    {
        return $this->patient;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    #[Groups(['journal_entry:read'])]
    public function getPatientId(): ?int
    {
        return $this->patient->getId();
    }

    #[Groups(['journal_entry:read'])]
    public function getAuthorId(): ?int
    {
        return $this->author->getId();
    }

    #[Groups(['journal_entry:read'])]
    public function isEnteredByCaregiver(): bool
    {
        return $this->author->getId() !== $this->patient->getId();
    }

    public function getMood(): int
    {
        return $this->mood;
    }

    public function getPainLevel(): int
    {
        return $this->painLevel;
    }

    public function getBloodPressure(): string
    {
        return $this->bloodPressure;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
