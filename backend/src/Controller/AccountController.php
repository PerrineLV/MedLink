<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\AccountService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class AccountController
{
    public function __construct(
        private readonly Security $security,
        private readonly AccountService $accountService,
    ) {
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->requireCurrentUser();

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'roles' => $user->getRoles(),
            'title' => $user->getTitle(),
            'createdAt' => $user->getCreatedAt()->format(\DATE_ATOM),
        ]);
    }

    #[Route('/api/me/password', name: 'api_me_change_password', methods: ['PATCH'])]
    public function changePassword(Request $request): JsonResponse
    {
        $user = $this->requireCurrentUser();

        try {
            $data = $this->decodeJsonBody($request);
            $this->accountService->changePassword(
                $user,
                $this->requireString($data, 'currentPassword'),
                $this->requireString($data, 'newPassword'),
            );
        } catch (HttpExceptionInterface $e) {
            return new JsonResponse(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return new JsonResponse(['message' => 'Mot de passe mis à jour.']);
    }

    #[Route('/api/me/email', name: 'api_me_change_email', methods: ['PATCH'])]
    public function changeEmail(Request $request): JsonResponse
    {
        $user = $this->requireCurrentUser();

        try {
            $data = $this->decodeJsonBody($request);
            $this->accountService->changeEmail(
                $user,
                $this->requireString($data, 'password'),
                $this->requireString($data, 'newEmail'),
            );
        } catch (HttpExceptionInterface $e) {
            return new JsonResponse(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return new JsonResponse(['message' => 'Adresse e-mail mise à jour.']);
    }

    #[Route('/api/me/export', name: 'api_me_export', methods: ['GET'])]
    public function export(): Response
    {
        $user = $this->requireCurrentUser();
        $data = $this->accountService->exportData($user);

        $filename = sprintf('medlink_export_%d_%s.json', $user->getId(), (new \DateTimeImmutable())->format('Y-m-d'));

        return new JsonResponse($data, Response::HTTP_OK, [
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    #[Route('/api/me', name: 'api_me_delete', methods: ['DELETE'])]
    public function delete(Request $request): JsonResponse
    {
        $user = $this->requireCurrentUser();

        try {
            $data = $this->decodeJsonBody($request);
            $this->accountService->deleteAccount($user, $this->requireString($data, 'password'));
        } catch (HttpExceptionInterface $e) {
            return new JsonResponse(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return new JsonResponse(['message' => 'Compte supprimé.']);
    }

    private function requireCurrentUser(): User
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Utilisateur non authentifié.');
        }

        return $user;
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
    private function requireString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || '' === $value) {
            throw new BadRequestHttpException(sprintf('Le champ "%s" est obligatoire.', $key));
        }

        return $value;
    }
}
