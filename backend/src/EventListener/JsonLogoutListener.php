<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Runs before Symfony's DefaultLogoutListener (priority 64) so the stateless
 * API returns a JSON body instead of the default redirect response.
 */
#[AsEventListener(event: LogoutEvent::class, method: 'onLogout', priority: 128, dispatcher: 'security.event_dispatcher.main')]
final class JsonLogoutListener
{
    public function onLogout(LogoutEvent $event): void
    {
        $event->setResponse(new JsonResponse(['message' => 'Déconnexion réussie.']));
    }
}
