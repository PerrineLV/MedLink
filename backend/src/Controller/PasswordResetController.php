<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PasswordResetMailer;
use App\Service\PasswordResetService;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class PasswordResetController
{
    private const GENERIC_REQUEST_MESSAGE = 'Si un compte existe avec cette adresse, un email de réinitialisation a été envoyé.';

    public function __construct(
        private readonly PasswordResetService $passwordResetService,
        private readonly RateLimiterFactory $passwordResetRequestByIpLimiter,
    ) {
    }

    #[Route('/api/password-reset/request', name: 'api_password_reset_request', methods: ['POST'])]
    public function request(Request $request): JsonResponse
    {
        if (!$this->passwordResetRequestByIpLimiter->create($request->getClientIp())->consume(1)->isAccepted()) {
            return new JsonResponse(
                ['message' => 'Trop de demandes de réinitialisation. Réessayez plus tard.'],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        try {
            $data = $this->decodeJsonBody($request);
            $this->passwordResetService->requestReset(
                $this->requireString($data, 'email'),
                $this->requirePlatform($data),
            );
        } catch (HttpExceptionInterface $e) {
            return new JsonResponse(['message' => $e->getMessage()], $e->getStatusCode());
        }

        // Toujours la même réponse, compte existant ou non (anti-énumération, ML-78).
        return new JsonResponse(['message' => self::GENERIC_REQUEST_MESSAGE]);
    }

    #[Route('/api/password-reset/confirm', name: 'api_password_reset_confirm', methods: ['POST'])]
    public function confirm(Request $request): JsonResponse
    {
        try {
            $data = $this->decodeJsonBody($request);
            $this->passwordResetService->confirmReset(
                $this->requireString($data, 'token'),
                $this->requireString($data, 'newPassword'),
            );
        } catch (HttpExceptionInterface $e) {
            return new JsonResponse(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return new JsonResponse(['message' => 'Mot de passe réinitialisé.']);
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

    /**
     * @param array<string, mixed> $data
     */
    private function requirePlatform(array $data): string
    {
        $platform = $data['platform'] ?? PasswordResetMailer::PLATFORM_WEB;
        if (!in_array($platform, [PasswordResetMailer::PLATFORM_WEB, PasswordResetMailer::PLATFORM_MOBILE], true)) {
            throw new BadRequestHttpException('Le champ "platform" doit valoir "web" ou "mobile".');
        }

        return $platform;
    }
}
