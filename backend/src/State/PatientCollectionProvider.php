<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\VisiblePatientIds;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Lists the patients the current user can act on behalf of: themselves for
 * a patient, their actively attached patients for an aidant or soignant.
 * Used by the mobile app to let an aidant pick which patient a new journal
 * entry is for.
 *
 * @implements ProviderInterface<User>
 */
final class PatientCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly VisiblePatientIds $visiblePatientIds,
        private readonly UserRepository $userRepository,
        private readonly Security $security,
    ) {
    }

    /**
     * @return list<User>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $currentUser = $this->security->getUser();
        if (!$currentUser instanceof User) {
            return [];
        }

        $patientIds = $this->visiblePatientIds->forUser($currentUser);
        if ([] === $patientIds) {
            return [];
        }

        return $this->userRepository->findBy(['id' => $patientIds]);
    }
}
