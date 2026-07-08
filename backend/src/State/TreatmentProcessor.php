<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\TreatmentInput;
use App\Entity\Treatment;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\TreatmentIntakeService;
use App\Service\TreatmentService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<TreatmentInput, Treatment>
 */
final class TreatmentProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly TreatmentService $treatmentService,
        private readonly UserRepository $userRepository,
        private readonly TreatmentIntakeService $treatmentIntakeService,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Treatment
    {
        $patient = $this->userRepository->find($data->patientId);
        if (!$patient instanceof User || !in_array(User::ROLE_PATIENT, $patient->getRoles(), true)) {
            throw new NotFoundHttpException('Patient introuvable.');
        }

        $treatment = $this->treatmentService->create($patient, $data->name, $data->dosage, $data->schedules);

        // Résout le statut "pris aujourd'hui" de chaque horaire fraîchement
        // créé, comme le fait TreatmentCollectionProvider en lecture, pour
        // que la réponse de création ait la même forme qu'un GET.
        $today = new \DateTimeImmutable('today');
        foreach ($treatment->getSchedules() as $schedule) {
            $schedule->setTodayIntake($this->treatmentIntakeService->findOrCreateForDate($schedule, $today));
        }

        return $treatment;
    }
}
