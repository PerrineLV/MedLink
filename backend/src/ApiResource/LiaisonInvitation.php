<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\LiaisonInvitationInput;
use App\Entity\PatientAidant;
use App\Entity\PatientSoignant;
use App\Entity\User;
use App\State\LiaisonInvitationAcceptProcessor;
use App\State\LiaisonInvitationProcessor;
use App\State\LiaisonInvitationProvider;
use App\State\LiaisonInvitationRejectProcessor;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/liaisons/invitations',
            processor: LiaisonInvitationProcessor::class,
            input: LiaisonInvitationInput::class,
        ),
        new Patch(
            uriTemplate: '/liaisons/invitations/{id}/accepter',
            deserialize: false,
            provider: LiaisonInvitationProvider::class,
            processor: LiaisonInvitationAcceptProcessor::class,
            security: "is_granted('LIAISON_INVITATION_RESPOND', object)",
        ),
        new Patch(
            uriTemplate: '/liaisons/invitations/{id}/refuser',
            deserialize: false,
            provider: LiaisonInvitationProvider::class,
            processor: LiaisonInvitationRejectProcessor::class,
            security: "is_granted('LIAISON_INVITATION_RESPOND', object)",
        ),
    ],
)]
final class LiaisonInvitation
{
    public function __construct(
        // Préfixé par type ("aidant-12" / "soignant-7") : PatientAidant et
        // PatientSoignant ont chacun leur propre séquence PostgreSQL, un id
        // numérique nu serait donc ambigu entre les deux tables.
        #[ApiProperty(identifier: true)]
        public readonly string $id,
        public readonly int $patientId,
        public readonly int $inviteeId,
        public readonly string $inviteeRole,
        public readonly bool $active,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function forAidantRelation(PatientAidant $relation): self
    {
        return new self(
            sprintf('aidant-%d', $relation->getId()),
            (int) $relation->getPatient()->getId(),
            (int) $relation->getAidant()->getId(),
            User::ROLE_AIDANT,
            $relation->isActive(),
            $relation->getCreatedAt(),
        );
    }

    public static function forSoignantRelation(PatientSoignant $relation): self
    {
        return new self(
            sprintf('soignant-%d', $relation->getId()),
            (int) $relation->getPatient()->getId(),
            (int) $relation->getSoignant()->getId(),
            User::ROLE_SOIGNANT,
            $relation->isActive(),
            $relation->getCreatedAt(),
        );
    }
}
