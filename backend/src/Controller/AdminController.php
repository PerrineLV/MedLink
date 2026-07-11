<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Security\Voter\AdminVoter;
use App\Service\AdminUserService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController
{
    private const DEFAULT_PER_PAGE = 20;
    private const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly Security $security,
        private readonly AdminUserService $adminUserService,
    ) {
    }

    #[Route('/api/admin/users', name: 'api_admin_users_list', methods: ['GET'])]
    public function listUsers(Request $request): JsonResponse
    {
        $this->denyAccessUnlessAdmin();

        $page = max(1, $request->query->getInt('page', 1));
        $perPage = min(self::MAX_PER_PAGE, max(1, $request->query->getInt('perPage', self::DEFAULT_PER_PAGE)));

        try {
            $result = $this->adminUserService->listUsers(
                $request->query->get('role'),
                $request->query->get('status'),
                $page,
                $perPage,
            );
        } catch (HttpExceptionInterface $e) {
            return new JsonResponse(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return new JsonResponse([
            'items' => array_map($this->serializeUser(...), $result['items']),
            'total' => $result['total'],
            'page' => $page,
            'perPage' => $perPage,
        ]);
    }

    #[Route('/api/admin/users/{id}/status', name: 'api_admin_user_status', methods: ['PATCH'])]
    public function updateStatus(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessAdmin();

        try {
            $data = $this->decodeJsonBody($request);
            $active = $this->requireBool($data, 'active');
            $user = $this->adminUserService->setUserActive($id, $active);
        } catch (HttpExceptionInterface $e) {
            return new JsonResponse(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return new JsonResponse($this->serializeUser($user));
    }

    private function denyAccessUnlessAdmin(): void
    {
        if (!$this->security->isGranted(AdminVoter::MANAGE_USERS)) {
            throw new AccessDeniedHttpException('Accès réservé aux administrateurs.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'roles' => $user->getRoles(),
            'active' => $user->isActive(),
            'createdAt' => $user->getCreatedAt()->format(\DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonBody(Request $request): array
    {
        try {
            return $request->toArray();
        } catch (JsonException) {
            throw new BadRequestHttpException('Corps de requête JSON invalide.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requireBool(array $data, string $key): bool
    {
        $value = $data[$key] ?? null;
        if (!is_bool($value)) {
            throw new BadRequestHttpException(sprintf('Le champ "%s" est obligatoire et doit être un booléen.', $key));
        }

        return $value;
    }
}
