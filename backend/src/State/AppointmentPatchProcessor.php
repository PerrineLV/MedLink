<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\AppointmentPatchInput;
use App\Entity\Appointment;
use App\Exception\InvalidAppointmentException;
use App\Service\AppointmentService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<AppointmentPatchInput, Appointment>
 */
final class AppointmentPatchProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Appointment
    {
        $appointment = $context['read_data'] ?? null;
        if (!$appointment instanceof Appointment) {
            throw new NotFoundHttpException('Rendez-vous introuvable.');
        }

        $scheduledAt = null !== $data->scheduledAt ? $this->parseScheduledAt($data->scheduledAt) : null;

        return $this->appointmentService->update($appointment, $scheduledAt, $data->status, $data->notes);
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
