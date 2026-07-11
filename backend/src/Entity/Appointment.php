<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\AppointmentInput;
use App\Dto\AppointmentPatchInput;
use App\Repository\AppointmentRepository;
use App\State\AppointmentCollectionProvider;
use App\State\AppointmentDeleteProcessor;
use App\State\AppointmentPatchProcessor;
use App\State\AppointmentProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: AppointmentRepository::class)]
#[ORM\Table(name: 'appointment')]
#[ApiResource(
    operations: [
        new GetCollection(provider: AppointmentCollectionProvider::class),
        new Post(processor: AppointmentProcessor::class, input: AppointmentInput::class),
        new Patch(processor: AppointmentPatchProcessor::class, input: AppointmentPatchInput::class),
        new Delete(processor: AppointmentDeleteProcessor::class),
    ],
    normalizationContext: ['groups' => ['appointment:read']],
)]
class Appointment
{
    public const STATUS_PLANNED = 'planned';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['appointment:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $patient;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $soignant;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['appointment:read'])]
    private \DateTimeImmutable $scheduledAt;

    #[ORM\Column(length: 20)]
    #[Groups(['appointment:read'])]
    private string $status;

    #[ORM\Column(length: 1000, nullable: true)]
    #[Groups(['appointment:read'])]
    private ?string $notes;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['appointment:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $patient, User $soignant, \DateTimeImmutable $scheduledAt, ?string $notes = null)
    {
        $this->patient = $patient;
        $this->soignant = $soignant;
        $this->scheduledAt = $scheduledAt;
        $this->notes = $notes;
        $this->status = self::STATUS_PLANNED;
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

    public function getSoignant(): User
    {
        return $this->soignant;
    }

    #[Groups(['appointment:read'])]
    public function getPatientId(): ?int
    {
        return $this->patient->getId();
    }

    #[Groups(['appointment:read'])]
    public function getSoignantId(): ?int
    {
        return $this->soignant->getId();
    }

    public function getScheduledAt(): \DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(\DateTimeImmutable $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
