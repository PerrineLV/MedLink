<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\AppVersionController;
use PHPUnit\Framework\TestCase;

final class AppVersionControllerTest extends TestCase
{
    public function testReturnsVersionAndApkUrlAsJson(): void
    {
        $response = (new AppVersionController('1.2.0', 'https://medlink-app.fr/downloads/medlink-latest.apk'))->__invoke();

        self::assertSame(200, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            '{"version":"1.2.0","apk_url":"https://medlink-app.fr/downloads/medlink-latest.apk"}',
            $response->getContent(),
        );
    }
}
