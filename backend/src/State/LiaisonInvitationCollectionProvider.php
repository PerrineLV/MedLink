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
 * Liste les liaisons (actives ou en attente) du patient courant, pour
 * l'écran "Mes liaisons" (ML-47). Les liens révoqués (revokedAt non nul)
 * sont exclus : une fois révoqué, un lien ne doit réapparaître dans
 * aucune section de l'écran.
 *
 * @implements ProviderInterface<LiaisonInvitation>
 */
final class LiaisonInvitationCollectionProvider implements ProviderInterface
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
        $patient = $this->security->getUser();
        if (!$patient instanceof User) {
            return [];
        }

        $invitations = [
            ...array_map(
                LiaisonInvitation::forAidantRelation(...),
                $this->patientAidantRepository->findVisibleForPatient($patient),
            ),
            ...array_map(
                LiaisonInvitation::forSoignantRelation(...),
                $this->patientSoignantRepository->findVisibleForPatient($patient),
            ),
        ];

        usort($invitations, static fn (LiaisonInvitation $a, LiaisonInvitation $b): int => $b->createdAt <=> $a->createdAt);

        return $invitations;
    }
}
