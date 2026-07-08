<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\State\MedicationSearchProvider;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/medications/search',
            security: "is_granted('ROLE_SOIGNANT')",
            provider: MedicationSearchProvider::class,
        ),
    ],
)]
final class MedicationSuggestion
{
    public function __construct(
        // Identifiant positionnel, pas le nom : environ 90 dénominations BDPM
        // contiennent un "/" (ex. associations "amoxicilline/acide
        // clavulanique"), ce qui casse la génération d'IRI si le nom sert
        // d'identifiant de route.
        #[ApiProperty(identifier: true)]
        public readonly int $id,
        public readonly string $name,
        // Best-effort, extrait de $name (pas un champ BDPM structuré) :
        // toujours modifiable côté formulaire, jamais imposé.
        public readonly ?string $suggestedDosage = null,
    ) {
    }
}
