<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TreatmentIntake;
use App\Entity\TreatmentSchedule;
use App\Repository\TreatmentIntakeRepository;
use Doctrine\ORM\EntityManagerInterface;

final class TreatmentIntakeService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TreatmentIntakeRepository $treatmentIntakeRepository,
    ) {
    }

    /**
     * Crée à la demande la ligne de suivi du jour si elle n'existe pas encore
     * (pas besoin de cron) : un jour différent repart d'un statut "à
     * prendre", sans reporter le statut de la veille.
     */
    public function findOrCreateForDate(TreatmentSchedule $schedule, \DateTimeImmutable $date): TreatmentIntake
    {
        $existing = $this->treatmentIntakeRepository->findOneByScheduleAndDate($schedule, $date);
        if (null !== $existing) {
            return $existing;
        }

        $intake = new TreatmentIntake($schedule, $date);

        $this->entityManager->persist($intake);
        $this->entityManager->flush();

        return $intake;
    }

    public function toggle(TreatmentIntake $intake): TreatmentIntake
    {
        if ($intake->isTaken()) {
            $intake->markNotTaken();
        } else {
            $intake->markTaken(new \DateTimeImmutable());
        }

        $this->entityManager->flush();

        return $intake;
    }
}
