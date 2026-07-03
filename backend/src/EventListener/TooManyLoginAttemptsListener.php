<?php

declare(strict_types=1);

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

/**
 * lexik's default failure handler always responds 401 unless the exception
 * code maps to a 4xx status, which TooManyLoginAttemptsAuthenticationException
 * does not set. This corrects the response to 429 with an explicit message.
 */
#[AsEventListener(event: Events::AUTHENTICATION_FAILURE, method: 'onAuthenticationFailure')]
final class TooManyLoginAttemptsListener
{
    public function onAuthenticationFailure(AuthenticationFailureEvent $event): void
    {
        $exception = $event->getException();
        if (!$exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return;
        }

        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        $event->setResponse(new JsonResponse(['message' => $message], Response::HTTP_TOO_MANY_REQUESTS));
    }
}
