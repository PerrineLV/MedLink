<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\TreatmentPatchInput;
use App\Entity\Treatment;
use App\Service\TreatmentService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<TreatmentPatchInput, Treatment>
 */
final class TreatmentPatchProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly TreatmentService $treatmentService,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Treatment
    {
        $treatment = $context['read_data'] ?? null;
        if (!$treatment instanceof Treatment) {
            throw new NotFoundHttpException('Traitement introuvable.');
        }

        return $this->treatmentService->update(
            $treatment,
            $data->name,
            $data->dosage,
            $data->scheduledTime,
            $data->active,
        );
    }
}
