<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'treatment_schedule')]
class TreatmentSchedule
{
    public const MOMENT_MORNING = 'morning';
    public const MOMENT_NOON = 'noon';
    public const MOMENT_EVENING = 'evening';
    public const MOMENT_CUSTOM = 'custom';

    /**
     * @var array<string, int>
     */
    private const POSITION_BY_MOMENT = [
        self::MOMENT_MORNING => 0,
        self::MOMENT_NOON => 1,
        self::MOMENT_EVENING => 2,
        self::MOMENT_CUSTOM => 3,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['treatment:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Treatment::class, inversedBy: 'schedules')]
    #[ORM\JoinColumn(nullable: false)]
    private Treatment $treatment;

    #[ORM\Column(length: 20)]
    #[Groups(['treatment:read'])]
    private string $moment;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['treatment:read'])]
    private ?string $customLabel;

    /**
     * Ordre d'affichage (matin < midi < soir < personnalisé), dérivé de
     * $moment à la création : pas exposé via l'API, ni modifiable
     * indépendamment du moment.
     */
    #[ORM\Column]
    private int $position;

    /**
     * Statut de prise du jour demandé, résolu par TreatmentCollectionProvider.
     * Non persisté : dépend de la date de la requête, pas une relation Doctrine.
     */
    #[Groups(['treatment:read'])]
    private ?TreatmentIntake $todayIntake = null;

    public function __construct(Treatment $treatment, string $moment, ?string $customLabel = null)
    {
        $this->treatment = $treatment;
        $this->moment = $moment;
        $this->customLabel = $customLabel;
        $this->position = self::POSITION_BY_MOMENT[$moment] ?? 99;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTreatment(): Treatment
    {
        return $this->treatment;
    }

    public function getMoment(): string
    {
        return $this->moment;
    }

    public function getCustomLabel(): ?string
    {
        return $this->customLabel;
    }

    public function getTodayIntake(): ?TreatmentIntake
    {
        return $this->todayIntake;
    }

    public function setTodayIntake(?TreatmentIntake $todayIntake): static
    {
        $this->todayIntake = $todayIntake;

        return $this;
    }
}
