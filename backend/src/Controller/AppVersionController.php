<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class AppVersionController extends AbstractController
{
    public function __construct(
        private readonly string $version,
        private readonly string $apkDownloadUrl,
    ) {
    }

    #[Route('/api/app-version', name: 'app_version', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'version' => $this->version,
            'apk_url' => $this->apkDownloadUrl,
        ]);
    }
}
