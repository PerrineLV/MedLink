<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Message;
use App\Entity\User;
use App\Repository\PatientAidantRepository;
use App\Repository\PatientSoignantRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Contrôle les actions sur la messagerie :
 *  - SEND : envoyer un message à un destinataire (subject), réservé aux
 *    utilisateurs rattachés l'un à l'autre par une relation active
 *    (patient/aidant ou patient/soignant), quel que soit le sens ;
 *  - MARK_READ : marquer un message comme lu, réservé au destinataire du
 *    message.
 *
 * @extends Voter<'MESSAGE_SEND'|'MESSAGE_MARK_READ', Message|User>
 */
final class MessageVoter extends Voter
{
    public const SEND = 'MESSAGE_SEND';
    public const MARK_READ = 'MESSAGE_MARK_READ';

    public function __construct(
        private readonly PatientAidantRepository $patientAidantRepository,
        private readonly PatientSoignantRepository $patientSoignantRepository,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (self::SEND === $attribute) {
            return $subject instanceof User;
        }

        return self::MARK_READ === $attribute && $subject instanceof Message;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();
        if (!$currentUser instanceof User) {
            return false;
        }

        if (self::MARK_READ === $attribute) {
            /* @var Message $subject */
            return null !== $subject->getRecipient()->getId()
                && $subject->getRecipient()->getId() === $currentUser->getId();
        }

        /** @var User $recipient */
        $recipient = $subject;

        return $this->areLinked($currentUser, $recipient);
    }

    private function areLinked(User $a, User $b): bool
    {
        return $this->patientAidantRepository->hasActiveRelation($a, $b)
            || $this->patientAidantRepository->hasActiveRelation($b, $a)
            || $this->patientSoignantRepository->hasActiveRelation($a, $b)
            || $this->patientSoignantRepository->hasActiveRelation($b, $a);
    }
}
