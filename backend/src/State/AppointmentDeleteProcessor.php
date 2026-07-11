<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Appointment;
use App\Service\AppointmentService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<mixed, void>
 */
final class AppointmentDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof Appointment) {
            throw new NotFoundHttpException('Rendez-vous introuvable.');
        }

        $this->appointmentService->delete($data);
    }
}
