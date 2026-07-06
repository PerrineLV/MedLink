<?php

declare(strict_types=1);

namespace App\Security\RateLimiter;

use Symfony\Component\HttpFoundation\RateLimiter\AbstractRequestRateLimiter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Limits login attempts by client IP only (5 per minute, see
 * config/packages/rate_limiter.yaml), regardless of the username being
 * attempted — unlike Symfony's DefaultLoginRateLimiter, which also limits
 * by username+IP.
 */
final class IpLoginRateLimiter extends AbstractRequestRateLimiter
{
    public function __construct(
        private readonly RateLimiterFactory $loginByIpLimiter,
    ) {
    }

    protected function getLimiters(Request $request): array
    {
        return [$this->loginByIpLimiter->create($request->getClientIp())];
    }
}
