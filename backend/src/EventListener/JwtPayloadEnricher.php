<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Adds the user's first name to the JWT payload so clients can greet them
 * ("Bonjour, Marie") without an extra round trip — the default payload only
 * carries the identifier (email) and roles.
 */
#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created', method: 'onJwtCreated')]
final class JwtPayloadEnricher
{
    public function onJwtCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $event->setData([
            ...$event->getData(),
            'firstName' => $user->getFirstName(),
        ]);
    }
}
