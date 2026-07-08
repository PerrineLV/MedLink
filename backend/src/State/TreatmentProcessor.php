<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\TreatmentInput;
use App\Entity\Treatment;
use App\Entity\User;
use App\Repository\UserRepository;
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
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Treatment
    {
        $patient = $this->userRepository->find($data->patientId);
        if (!$patient instanceof User || !in_array(User::ROLE_PATIENT, $patient->getRoles(), true)) {
            throw new NotFoundHttpException('Patient introuvable.');
        }

        return $this->treatmentService->create($patient, $data->name, $data->dosage, $data->scheduledTime);
    }
}
