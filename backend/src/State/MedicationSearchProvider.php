<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MedicationSuggestion;
use App\Service\MedicationReferenceService;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<MedicationSuggestion>
 */
final class MedicationSearchProvider implements ProviderInterface
{
    public function __construct(
        private readonly MedicationReferenceService $medicationReferenceService,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return list<MedicationSuggestion>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $query = (string) $this->requestStack->getCurrentRequest()?->query->get('q', '');
        $names = $this->medicationReferenceService->search($query);

        return array_map(
            fn (int $index, string $name): MedicationSuggestion => new MedicationSuggestion(
                $index,
                $name,
                $this->medicationReferenceService->suggestDosage($name),
            ),
            array_keys($names),
            $names,
        );
    }
}
