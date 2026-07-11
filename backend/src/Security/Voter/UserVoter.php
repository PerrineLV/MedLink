<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Repository\PatientAidantRepository;
use App\Repository\PatientSoignantRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Controls access to a patient's data (subject is the target User, who must
 * have ROLE_PATIENT):
 *  - the patient themselves can view and manage their own data;
 *  - an aidant can view and manage the data of a patient they are actively
 *    attached to (App\Entity\PatientAidant);
 *  - a soignant can only view the data of a patient they are actively the
 *    referent of (App\Entity\PatientSoignant) — reading, not acting on
 *    behalf of the patient.
 *
 * A relation with active = false grants no access.
 *
 * @extends Voter<'PATIENT_VIEW'|'PATIENT_MANAGE', User>
 */
final class UserVoter extends Voter
{
    public const VIEW = 'PATIENT_VIEW';
    public const MANAGE = 'PATIENT_MANAGE';

    public function __construct(
        private readonly PatientAidantRepository $patientAidantRepository,
        private readonly PatientSoignantRepository $patientSoignantRepository,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::MANAGE], true)
            && $subject instanceof User
            && in_array(User::ROLE_PATIENT, $subject->getRoles(), true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();
        if (!$currentUser instanceof User) {
            return false;
        }

        /** @var User $patient */
        $patient = $subject;

        if (null !== $patient->getId() && $patient->getId() === $currentUser->getId()) {
            return true;
        }

        if (in_array(User::ROLE_AIDANT, $currentUser->getRoles(), true)
            && $this->patientAidantRepository->hasActiveRelation($patient, $currentUser)) {
            return true;
        }

        if (self::VIEW === $attribute
            && in_array(User::ROLE_SOIGNANT, $currentUser->getRoles(), true)
            && $this->patientSoignantRepository->hasActiveRelation($patient, $currentUser)) {
            return true;
        }

        return false;
    }
}
