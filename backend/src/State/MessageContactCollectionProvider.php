<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MessageContact;
use App\Entity\User;
use App\Security\MessageableContacts;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Liste les contacts que l'utilisateur courant a le droit de contacter par
 * messagerie (ML-70), pour l'écran Messagerie (ML-26). Le calcul exact
 * (patient -> ses soignants, aidant -> soignant(s) du patient commun,
 * soignant -> patients + aidants de ces patients) vit dans
 * MessageableContacts, partagé avec MessageVoter pour ne jamais diverger de
 * ce qui est réellement autorisé à l'envoi.
 *
 * @implements ProviderInterface<MessageContact>
 */
final class MessageContactCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly MessageableContacts $messageableContacts,
        private readonly Security $security,
    ) {
    }

    /**
     * @return list<MessageContact>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        return array_map(
            MessageContact::fromMessageableContact(...),
            $this->messageableContacts->forUser($user),
        );
    }
}
