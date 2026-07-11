<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\FailedLoginAttempt;
use App\EventListener\FailedLoginAttemptListener;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

final class FailedLoginAttemptListenerTest extends TestCase
{
    public function testItPersistsAndFlushesAFailedLoginAttempt(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(FailedLoginAttempt::class));
        $entityManager->expects(self::once())->method('flush');

        $event = new AuthenticationFailureEvent(new BadCredentialsException(), new JsonResponse());

        (new FailedLoginAttemptListener($entityManager))->onAuthenticationFailure($event);
    }
}
