<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\User;
use App\EventListener\JwtPayloadEnricher;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

final class JwtPayloadEnricherTest extends TestCase
{
    public function testItAddsTheFirstNameToThePayload(): void
    {
        $user = new User('marie@medlink.test', 'Marie', 'Dupont');
        $event = new JWTCreatedEvent(['roles' => ['ROLE_PATIENT'], 'username' => 'marie@medlink.test'], $user);

        (new JwtPayloadEnricher())->onJwtCreated($event);

        self::assertSame(
            ['roles' => ['ROLE_PATIENT'], 'username' => 'marie@medlink.test', 'firstName' => 'Marie'],
            $event->getData(),
        );
    }

    public function testItLeavesThePayloadUntouchedForANonAppUser(): void
    {
        $user = $this->createStub(UserInterface::class);
        $event = new JWTCreatedEvent(['roles' => []], $user);

        (new JwtPayloadEnricher())->onJwtCreated($event);

        self::assertSame(['roles' => []], $event->getData());
    }
}
