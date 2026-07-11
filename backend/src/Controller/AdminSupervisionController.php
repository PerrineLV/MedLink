<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\Voter\AdminVoter;
use App\Service\AdminSupervisionService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class AdminSupervisionController
{
    public function __construct(
        private readonly Security $security,
        private readonly AdminSupervisionService $adminSupervisionService,
    ) {
    }

    #[Route('/api/admin/health-summary', name: 'api_admin_health_summary', methods: ['GET'])]
    public function healthSummary(): JsonResponse
    {
        if (!$this->security->isGranted(AdminVoter::VIEW_SUPERVISION)) {
            throw new AccessDeniedHttpException('Accès réservé aux administrateurs.');
        }

        return new JsonResponse($this->adminSupervisionService->getHealthSummary());
    }
}
