<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\AppointmentInput;
use App\Entity\Appointment;
use App\Entity\User;
use App\Exception\InvalidAppointmentException;
use App\Repository\UserRepository;
use App\Service\AppointmentService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<AppointmentInput, Appointment>
 */
final class AppointmentProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Appointment
    {
        $patient = $this->userRepository->find($data->patientId);
        if (!$patient instanceof User || !in_array(User::ROLE_PATIENT, $patient->getRoles(), true)) {
            throw new NotFoundHttpException('Patient introuvable.');
        }

        return $this->appointmentService->create($patient, $this->parseScheduledAt($data->scheduledAt), $data->notes);
    }

    private function parseScheduledAt(string $scheduledAt): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($scheduledAt);
        } catch (\Exception) {
            throw new InvalidAppointmentException('La date du rendez-vous est invalide.');
        }
    }
}
