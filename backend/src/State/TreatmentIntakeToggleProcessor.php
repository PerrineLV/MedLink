<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\TreatmentIntake;
use App\Service\TreatmentIntakeService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<null, TreatmentIntake>
 */
final class TreatmentIntakeToggleProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly TreatmentIntakeService $treatmentIntakeService,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TreatmentIntake
    {
        $intake = $context['read_data'] ?? null;
        if (!$intake instanceof TreatmentIntake) {
            throw new NotFoundHttpException('Prise de traitement introuvable.');
        }

        return $this->treatmentIntakeService->toggle($intake);
    }
}
