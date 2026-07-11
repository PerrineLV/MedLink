<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\PatientAidant;
use App\Entity\PatientSoignant;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Contrôle les actions sur une liaison patient/aidant ou patient/soignant
 * (ces deux constantes couvrent des rôles symétriques et opposés sur le même
 * lien, d'où leur regroupement dans un seul Voter) :
 *  - RESPOND : répondre (accepter/refuser) à une invitation en attente,
 *    réservé au destinataire de l'invitation (l'aidant ou le soignant
 *    invité) — jamais le patient à l'origine de l'invitation, ni un tiers ;
 *  - REVOKE : révoquer un lien, réservé au patient propriétaire du lien —
 *    jamais l'aidant/soignant concerné, ni un tiers.
 *
 * @extends Voter<'LIAISON_INVITATION_RESPOND'|'LIAISON_REVOKE', PatientAidant|PatientSoignant>
 */
final class LiaisonInvitationVoter extends Voter
{
    public const RESPOND = 'LIAISON_INVITATION_RESPOND';
    public const REVOKE = 'LIAISON_REVOKE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::RESPOND, self::REVOKE], true)
            && ($subject instanceof PatientAidant || $subject instanceof PatientSoignant);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();
        if (!$currentUser instanceof User) {
            return false;
        }

        if (self::REVOKE === $attribute) {
            return $subject->getPatient() === $currentUser;
        }

        if ($subject instanceof PatientAidant) {
            return $subject->getAidant() === $currentUser;
        }

        return $subject->getSoignant() === $currentUser;
    }
}
