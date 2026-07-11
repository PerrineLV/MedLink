<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\EventListener\TooManyLoginAttemptsListener;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

final class TooManyLoginAttemptsListenerTest extends TestCase
{
    public function testItReturns429WithAnExplicitMessageAfterTooManyAttempts(): void
    {
        $event = new AuthenticationFailureEvent(
            new TooManyLoginAttemptsAuthenticationException(1),
            new JsonResponse(['code' => 401, 'message' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED),
        );

        (new TooManyLoginAttemptsListener())->onAuthenticationFailure($event);

        $response = $event->getResponse();

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            '{"message":"Too many failed login attempts, please try again in 1 minute."}',
            (string) $response->getContent(),
        );
    }

    public function testItLeavesOtherAuthenticationFailuresUntouched(): void
    {
        $originalResponse = new JsonResponse(['code' => 401, 'message' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);

        $event = new AuthenticationFailureEvent(
            new BadCredentialsException(),
            $originalResponse,
        );

        (new TooManyLoginAttemptsListener())->onAuthenticationFailure($event);

        self::assertSame($originalResponse, $event->getResponse());
    }
}
