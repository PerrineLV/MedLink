<?php

declare(strict_types=1);

namespace App\Tests\Security\RateLimiter;

use App\Security\RateLimiter\IpLoginRateLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class IpLoginRateLimiterTest extends TestCase
{
    private IpLoginRateLimiter $limiter;

    protected function setUp(): void
    {
        $factory = new RateLimiterFactory(
            ['id' => 'login_by_ip', 'policy' => 'fixed_window', 'limit' => 5, 'interval' => '1 minute'],
            new InMemoryStorage(),
        );

        $this->limiter = new IpLoginRateLimiter($factory);
    }

    public function testTheSixthFailedAttemptFromTheSameIpIsRejected(): void
    {
        $request = $this->requestFromIp('203.0.113.10');

        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $result = $this->limiter->consume($request);
            self::assertTrue($result->isAccepted(), sprintf('Attempt #%d should be accepted.', $attempt));
        }

        $sixthAttempt = $this->limiter->consume($request);

        self::assertFalse($sixthAttempt->isAccepted());
        self::assertSame(0, $sixthAttempt->getRemainingTokens());
    }

    public function testTheLimitIsScopedPerIpNotGlobal(): void
    {
        $blockedIp = $this->requestFromIp('203.0.113.10');
        for ($attempt = 1; $attempt <= 6; ++$attempt) {
            $this->limiter->consume($blockedIp);
        }

        $otherIp = $this->requestFromIp('198.51.100.20');

        self::assertTrue($this->limiter->consume($otherIp)->isAccepted());
    }

    private function requestFromIp(string $ip): Request
    {
        $request = Request::create('/api/auth/login', 'POST');
        $request->server->set('REMOTE_ADDR', $ip);

        return $request;
    }
}
