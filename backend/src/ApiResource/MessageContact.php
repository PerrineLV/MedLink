<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Security\MessageableContact;
use App\State\MessageContactCollectionProvider;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/message-contacts',
            provider: MessageContactCollectionProvider::class,
        ),
    ],
)]
final class MessageContact
{
    /**
     * @param list<MessageContactPatient> $viaPatients
     */
    public function __construct(
        public readonly int $id,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $role,
        public readonly array $viaPatients,
    ) {
    }

    public static function fromMessageableContact(MessageableContact $contact): self
    {
        return new self(
            (int) $contact->user->getId(),
            $contact->user->getFirstName(),
            $contact->user->getLastName(),
            $contact->role,
            array_map(MessageContactPatient::fromUser(...), $contact->viaPatients),
        );
    }
}
