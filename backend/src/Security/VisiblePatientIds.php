<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\PatientAidantRepository;
use App\Repository\PatientSoignantRepository;

/**
 * Resolves the patient IDs a given user is allowed to see: themselves for a
 * patient, their actively attached patients for an aidant or soignant.
 */
final class VisiblePatientIds
{
    public function __construct(
        private readonly PatientAidantRepository $patientAidantRepository,
        private readonly PatientSoignantRepository $patientSoignantRepository,
    ) {
    }

    /**
     * @return list<int>
     */
    public function forUser(User $user): array
    {
        if (in_array(User::ROLE_PATIENT, $user->getRoles(), true)) {
            return [$user->getId()];
        }

        if (in_array(User::ROLE_AIDANT, $user->getRoles(), true)) {
            return $this->patientAidantRepository->findActivePatientIdsForAidant($user);
        }

        if (in_array(User::ROLE_SOIGNANT, $user->getRoles(), true)) {
            return $this->patientSoignantRepository->findActivePatientIdsForSoignant($user);
        }

        return [];
    }
}
