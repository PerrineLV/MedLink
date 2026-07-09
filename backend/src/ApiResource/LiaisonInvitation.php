<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\LiaisonInvitationInput;
use App\State\LiaisonInvitationProcessor;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/liaisons/invitations',
            processor: LiaisonInvitationProcessor::class,
            input: LiaisonInvitationInput::class,
        ),
    ],
)]
final class LiaisonInvitation
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public readonly int $id,
        public readonly int $patientId,
        public readonly int $inviteeId,
        public readonly string $inviteeRole,
        public readonly bool $active,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }
}
