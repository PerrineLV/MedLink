<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\EventListener\JsonLogoutListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class JsonLogoutListenerTest extends TestCase
{
    public function testItSetsAJsonResponseOnTheEvent(): void
    {
        $event = new LogoutEvent(new Request(), null);
        $listener = new JsonLogoutListener();

        $listener->onLogout($event);

        $response = $event->getResponse();

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            '{"message":"Déconnexion réussie."}',
            (string) $response->getContent(),
        );
    }
}
