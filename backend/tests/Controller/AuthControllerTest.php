<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\AuthController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * These actions are never reached in production: the requests are intercepted
 * upstream by the json_login, refresh_jwt and logout listeners (see
 * config/packages/security.yaml). This test only documents and locks that
 * contract: if one of these actions is ever actually invoked, something in
 * the security configuration has regressed.
 */
final class AuthControllerTest extends TestCase
{
    /**
     * @param 'login'|'refresh'|'logout' $action
     */
    #[DataProvider('provideActions')]
    public function testActionIsNeverMeantToBeReached(string $action): void
    {
        $this->expectException(\LogicException::class);

        (new AuthController())->{$action}();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideActions(): iterable
    {
        yield 'login' => ['login'];
        yield 'refresh' => ['refresh'];
        yield 'logout' => ['logout'];
    }
}
