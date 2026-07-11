<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\FailedLoginAttempt;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Records a failed login attempt for the admin supervision screen (ML-55).
 * Deliberately does not read the request (email, IP): only the count over
 * time matters here, never who or from where (A09, no personal data in
 * logs).
 */
#[AsEventListener(event: Events::AUTHENTICATION_FAILURE, method: 'onAuthenticationFailure')]
final class FailedLoginAttemptListener
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function onAuthenticationFailure(AuthenticationFailureEvent $event): void
    {
        $this->entityManager->persist(new FailedLoginAttempt());
        $this->entityManager->flush();
    }
}
