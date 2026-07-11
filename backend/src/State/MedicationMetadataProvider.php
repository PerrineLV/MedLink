<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MedicationMetadata;
use App\Service\MedicationReferenceService;

/**
 * @implements ProviderInterface<MedicationMetadata>
 */
final class MedicationMetadataProvider implements ProviderInterface
{
    public function __construct(
        private readonly MedicationReferenceService $medicationReferenceService,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MedicationMetadata
    {
        return new MedicationMetadata(extractedAt: $this->medicationReferenceService->extractedAt());
    }
}
