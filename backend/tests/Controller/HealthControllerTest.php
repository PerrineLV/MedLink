<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\HealthController;
use PHPUnit\Framework\TestCase;

final class HealthControllerTest extends TestCase
{
    public function testReturnsOkStatusAsJson(): void
    {
        $response = (new HealthController())->__invoke();

        self::assertSame(200, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString('{"status":"ok"}', $response->getContent());
    }
}
