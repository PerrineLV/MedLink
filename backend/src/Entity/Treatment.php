<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\TreatmentInput;
use App\Dto\TreatmentPatchInput;
use App\Repository\TreatmentRepository;
use App\State\TreatmentCollectionProvider;
use App\State\TreatmentPatchProcessor;
use App\State\TreatmentProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: TreatmentRepository::class)]
#[ORM\Table(name: 'treatment')]
#[ApiResource(
    operations: [
        new GetCollection(provider: TreatmentCollectionProvider::class),
        new Post(processor: TreatmentProcessor::class, input: TreatmentInput::class),
        new Patch(processor: TreatmentPatchProcessor::class, input: TreatmentPatchInput::class),
    ],
    normalizationContext: ['groups' => ['treatment:read']],
)]
class Treatment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['treatment:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $patient;

    #[ORM\Column(length: 255)]
    #[Groups(['treatment:read'])]
    private string $name;

    #[ORM\Column(length: 100)]
    #[Groups(['treatment:read'])]
    private string $dosage;

    /**
     * @var Collection<int, TreatmentSchedule>
     */
    #[ORM\OneToMany(mappedBy: 'treatment', targetEntity: TreatmentSchedule::class)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    #[Groups(['treatment:read'])]
    private Collection $schedules;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $prescribedBy;

    #[ORM\Column]
    #[Groups(['treatment:read'])]
    private bool $active = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['treatment:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        User $patient,
        string $name,
        string $dosage,
        User $prescribedBy,
    ) {
        $this->patient = $patient;
        $this->name = $name;
        $this->dosage = $dosage;
        $this->prescribedBy = $prescribedBy;
        $this->createdAt = new \DateTimeImmutable();
        $this->schedules = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPatient(): User
    {
        return $this->patient;
    }

    #[Groups(['treatment:read'])]
    public function getPatientId(): ?int
    {
        return $this->patient->getId();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDosage(): string
    {
        return $this->dosage;
    }

    public function setDosage(string $dosage): static
    {
        $this->dosage = $dosage;

        return $this;
    }

    /**
     * @return Collection<int, TreatmentSchedule>
     */
    public function getSchedules(): Collection
    {
        return $this->schedules;
    }

    public function addSchedule(TreatmentSchedule $schedule): static
    {
        $this->schedules->add($schedule);

        return $this;
    }

    public function clearSchedules(): static
    {
        $this->schedules->clear();

        return $this;
    }

    public function getPrescribedBy(): User
    {
        return $this->prescribedBy;
    }

    #[Groups(['treatment:read'])]
    public function getPrescribedById(): ?int
    {
        return $this->prescribedBy->getId();
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
