<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\MedicationMetadataProvider;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/medications/metadata',
            security: "is_granted('ROLE_SOIGNANT')",
            provider: MedicationMetadataProvider::class,
        ),
    ],
)]
final class MedicationMetadata
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public readonly string $id = 'medications-metadata',
        public readonly ?string $extractedAt = null,
    ) {
    }
}
