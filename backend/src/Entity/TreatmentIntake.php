<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Patch;
use App\Repository\TreatmentIntakeRepository;
use App\State\TreatmentIntakeToggleProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: TreatmentIntakeRepository::class)]
#[ORM\Table(name: 'treatment_intake')]
#[ORM\UniqueConstraint(name: 'uniq_treatment_intake_date', columns: ['treatment_id', 'date'])]
#[ApiResource(
    operations: [
        new Patch(
            uriTemplate: '/treatment-intakes/{id}/toggle',
            deserialize: false,
            security: "is_granted('PATIENT_MANAGE', object.getTreatment().getPatient())",
            processor: TreatmentIntakeToggleProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['treatment:read']],
)]
class TreatmentIntake
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['treatment:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Treatment::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Treatment $treatment;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Groups(['treatment:read'])]
    private \DateTimeImmutable $date;

    #[ORM\Column]
    #[Groups(['treatment:read'])]
    private bool $taken = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['treatment:read'])]
    private ?\DateTimeImmutable $takenAt = null;

    public function __construct(Treatment $treatment, \DateTimeImmutable $date)
    {
        $this->treatment = $treatment;
        $this->date = $date;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTreatment(): Treatment
    {
        return $this->treatment;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function isTaken(): bool
    {
        return $this->taken;
    }

    public function getTakenAt(): ?\DateTimeImmutable
    {
        return $this->takenAt;
    }

    public function markTaken(\DateTimeImmutable $at): static
    {
        $this->taken = true;
        $this->takenAt = $at;

        return $this;
    }

    public function markNotTaken(): static
    {
        $this->taken = false;
        $this->takenAt = null;

        return $this;
    }
}
