<?php

declare(strict_types=1);

namespace App\Service;

use App\ApiResource\LiaisonInvitation;
use App\Entity\PatientAidant;
use App\Entity\PatientSoignant;
use App\Entity\User;
use App\Repository\PatientAidantRepository;
use App\Repository\PatientSoignantRepository;
use App\Security\Voter\UserVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class LiaisonInvitationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PatientAidantRepository $patientAidantRepository,
        private readonly PatientSoignantRepository $patientSoignantRepository,
        private readonly Security $security,
    ) {
    }

    public function invite(User $invitee): LiaisonInvitation
    {
        $patient = $this->security->getUser();
        if (!$patient instanceof User) {
            throw new AccessDeniedException('Utilisateur non authentifié.');
        }

        // UserVoter::MANAGE avec le patient comme sujet et comme utilisateur
        // courant ne peut être accordé que par la branche "auto-accès" du
        // voter : un aidant ou un soignant n'a jamais ROLE_PATIENT sur son
        // propre compte, donc seul le patient lui-même passe ce contrôle.
        if (!$this->security->isGranted(UserVoter::MANAGE, $patient)) {
            throw new AccessDeniedException('Seul un patient peut inviter un aidant ou un soignant.');
        }

        if (in_array(User::ROLE_AIDANT, $invitee->getRoles(), true)) {
            return $this->createAidantInvitation($patient, $invitee);
        }

        return $this->createSoignantInvitation($patient, $invitee);
    }

    private function createAidantInvitation(User $patient, User $aidant): LiaisonInvitation
    {
        if (null !== $this->patientAidantRepository->findOneBy(['patient' => $patient, 'aidant' => $aidant])) {
            throw new ConflictHttpException('Ce lien est déjà actif ou en attente.');
        }

        $relation = new PatientAidant($patient, $aidant);
        $relation->setActive(false);

        $this->entityManager->persist($relation);
        $this->entityManager->flush();

        return LiaisonInvitation::forAidantRelation($relation);
    }

    private function createSoignantInvitation(User $patient, User $soignant): LiaisonInvitation
    {
        if (null !== $this->patientSoignantRepository->findOneBy(['patient' => $patient, 'soignant' => $soignant])) {
            throw new ConflictHttpException('Ce lien est déjà actif ou en attente.');
        }

        $relation = new PatientSoignant($patient, $soignant);
        $relation->setActive(false);

        $this->entityManager->persist($relation);
        $this->entityManager->flush();

        return LiaisonInvitation::forSoignantRelation($relation);
    }

    public function accept(PatientAidant|PatientSoignant $relation): LiaisonInvitation
    {
        if ($relation->isActive()) {
            throw new ConflictHttpException('Cette invitation a déjà été traitée.');
        }

        $relation->setActive(true);
        $this->entityManager->flush();

        return $relation instanceof PatientAidant
            ? LiaisonInvitation::forAidantRelation($relation)
            : LiaisonInvitation::forSoignantRelation($relation);
    }

    public function reject(PatientAidant|PatientSoignant $relation): LiaisonInvitation
    {
        if ($relation->isActive()) {
            throw new ConflictHttpException('Cette invitation a déjà été traitée.');
        }

        $invitation = $relation instanceof PatientAidant
            ? LiaisonInvitation::forAidantRelation($relation)
            : LiaisonInvitation::forSoignantRelation($relation);

        $this->entityManager->remove($relation);
        $this->entityManager->flush();

        return $invitation;
    }
}
