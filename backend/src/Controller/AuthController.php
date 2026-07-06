<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;

/**
 * These actions are never executed: the requests are intercepted upstream by the
 * json_login, refresh_jwt and logout listeners configured in security.yaml. The
 * routes only need to exist so the router does not 404 before the firewall
 * gets a chance to handle the request.
 */
class AuthController
{
    #[Route('/api/auth/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(): never
    {
        throw new \LogicException('This action is intercepted by the json_login authenticator and should never run.');
    }

    #[Route('/api/auth/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    public function refresh(): never
    {
        throw new \LogicException('This action is intercepted by the refresh_jwt authenticator and should never run.');
    }

    #[Route('/api/auth/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('This action is intercepted by the logout listener and should never run.');
    }
}
