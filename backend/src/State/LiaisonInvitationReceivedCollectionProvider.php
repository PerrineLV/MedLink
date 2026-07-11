<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\LiaisonInvitation;
use App\Entity\User;
use App\Repository\PatientAidantRepository;
use App\Repository\PatientSoignantRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Liste les invitations reçues et encore en attente de réponse de
 * l'aidant/soignant courant, pour l'écran "Invitations en attente" (ML-48).
 * Contrairement à LiaisonInvitationCollectionProvider (ML-47, côté patient),
 * seules les invitations non actives sont renvoyées : une fois acceptée ou
 * refusée, une invitation ne doit plus apparaître dans cette liste.
 *
 * @implements ProviderInterface<LiaisonInvitation>
 */
final class LiaisonInvitationReceivedCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly PatientAidantRepository $patientAidantRepository,
        private readonly PatientSoignantRepository $patientSoignantRepository,
        private readonly Security $security,
    ) {
    }

    /**
     * @return list<LiaisonInvitation>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        $invitations = [
            ...array_map(
                LiaisonInvitation::forAidantRelation(...),
                $this->patientAidantRepository->findPendingForAidant($user),
            ),
            ...array_map(
                LiaisonInvitation::forSoignantRelation(...),
                $this->patientSoignantRepository->findPendingForSoignant($user),
            ),
        ];

        usort($invitations, static fn (LiaisonInvitation $a, LiaisonInvitation $b): int => $b->createdAt <=> $a->createdAt);

        return $invitations;
    }
}
