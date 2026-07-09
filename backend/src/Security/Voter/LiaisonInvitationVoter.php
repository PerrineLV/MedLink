<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\PatientAidant;
use App\Entity\PatientSoignant;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Contrôle qui peut répondre (accepter/refuser) à une invitation de liaison
 * en attente : uniquement le destinataire de l'invitation (l'aidant ou le
 * soignant invité) — jamais le patient à l'origine de l'invitation, ni un
 * tiers.
 *
 * @extends Voter<'LIAISON_INVITATION_RESPOND', PatientAidant|PatientSoignant>
 */
final class LiaisonInvitationVoter extends Voter
{
    public const RESPOND = 'LIAISON_INVITATION_RESPOND';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::RESPOND === $attribute
            && ($subject instanceof PatientAidant || $subject instanceof PatientSoignant);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();
        if (!$currentUser instanceof User) {
            return false;
        }

        if ($subject instanceof PatientAidant) {
            return $subject->getAidant() === $currentUser;
        }

        return $subject->getSoignant() === $currentUser;
    }
}
