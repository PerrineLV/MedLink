<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\LiaisonInvitationInput;
use App\Entity\PatientAidant;
use App\Entity\PatientSoignant;
use App\Entity\User;
use App\State\LiaisonInvitationAcceptProcessor;
use App\State\LiaisonInvitationCollectionProvider;
use App\State\LiaisonInvitationProcessor;
use App\State\LiaisonInvitationProvider;
use App\State\LiaisonInvitationReceivedCollectionProvider;
use App\State\LiaisonInvitationRejectProcessor;
use App\State\LiaisonInvitationRevokeProcessor;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/liaisons',
            security: "is_granted('ROLE_PATIENT')",
            provider: LiaisonInvitationCollectionProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/liaisons/invitations',
            security: "is_granted('ROLE_AIDANT') or is_granted('ROLE_SOIGNANT')",
            provider: LiaisonInvitationReceivedCollectionProvider::class,
        ),
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
        new Patch(
            uriTemplate: '/liaisons/{id}/revoquer',
            deserialize: false,
            provider: LiaisonInvitationProvider::class,
            processor: LiaisonInvitationRevokeProcessor::class,
            security: "is_granted('LIAISON_REVOKE', object)",
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
        public readonly string $patientFirstName,
        public readonly string $patientLastName,
        public readonly int $inviteeId,
        public readonly string $inviteeRole,
        public readonly string $inviteeFirstName,
        public readonly string $inviteeLastName,
        public readonly bool $active,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function forAidantRelation(PatientAidant $relation): self
    {
        $patient = $relation->getPatient();
        $aidant = $relation->getAidant();

        return new self(
            sprintf('aidant-%d', $relation->getId()),
            (int) $patient->getId(),
            $patient->getFirstName(),
            $patient->getLastName(),
            (int) $aidant->getId(),
            User::ROLE_AIDANT,
            $aidant->getFirstName(),
            $aidant->getLastName(),
            $relation->isActive(),
            $relation->getCreatedAt(),
        );
    }

    public static function forSoignantRelation(PatientSoignant $relation): self
    {
        $patient = $relation->getPatient();
        $soignant = $relation->getSoignant();

        return new self(
            sprintf('soignant-%d', $relation->getId()),
            (int) $patient->getId(),
            $patient->getFirstName(),
            $patient->getLastName(),
            (int) $soignant->getId(),
            User::ROLE_SOIGNANT,
            $soignant->getFirstName(),
            $soignant->getLastName(),
            $relation->isActive(),
            $relation->getCreatedAt(),
        );
    }
}
