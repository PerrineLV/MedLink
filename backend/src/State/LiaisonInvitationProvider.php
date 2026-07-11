<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\PatientAidant;
use App\Entity\PatientSoignant;
use App\Repository\PatientAidantRepository;
use App\Repository\PatientSoignantRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Résout l'id composite ("aidant-{id}" / "soignant-{id}") d'une
 * LiaisonInvitation vers l'entité PatientAidant ou PatientSoignant réelle,
 * pour les opérations d'action (accepter/refuser) sur un item existant.
 *
 * @implements ProviderInterface<PatientAidant|PatientSoignant>
 */
final class LiaisonInvitationProvider implements ProviderInterface
{
    public function __construct(
        private readonly PatientAidantRepository $patientAidantRepository,
        private readonly PatientSoignantRepository $patientSoignantRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PatientAidant|PatientSoignant
    {
        $id = (string) ($uriVariables['id'] ?? '');

        if (1 !== preg_match('/^(aidant|soignant)-(\d+)$/', $id, $matches)) {
            throw new NotFoundHttpException('Invitation introuvable.');
        }

        $relation = 'aidant' === $matches[1]
            ? $this->patientAidantRepository->find((int) $matches[2])
            : $this->patientSoignantRepository->find((int) $matches[2]);

        if (null === $relation) {
            throw new NotFoundHttpException('Invitation introuvable.');
        }

        return $relation;
    }
}
