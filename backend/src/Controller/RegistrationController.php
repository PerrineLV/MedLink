<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\RegistrationInput;
use App\Service\RegistrationService;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController
{
    public function __construct(
        private readonly RegistrationService $registrationService,
        private readonly RateLimiterFactory $registerByIpLimiter,
    ) {
    }

    #[Route('/api/auth/register', name: 'api_auth_register', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->registerByIpLimiter->create($request->getClientIp())->consume(1)->isAccepted()) {
            return new JsonResponse(
                ['message' => "Trop de tentatives d'inscription. Réessayez plus tard."],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        try {
            $data = $request->toArray();
        } catch (JsonException) {
            return new JsonResponse(['message' => 'Corps de requête JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $input = new RegistrationInput(
                email: $this->requireString($data, 'email'),
                password: $this->requireString($data, 'password'),
                firstName: $this->requireString($data, 'firstName'),
                lastName: $this->requireString($data, 'lastName'),
                role: $this->requireString($data, 'role'),
                title: $this->optionalString($data, 'title'),
                consent: $this->requireBool($data, 'consent'),
            );

            $user = $this->registrationService->register($input);
        } catch (HttpExceptionInterface $e) {
            return new JsonResponse(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'roles' => $user->getRoles(),
            'title' => $user->getTitle(),
        ], Response::HTTP_CREATED);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requireString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || '' === trim($value)) {
            throw new BadRequestHttpException(sprintf('Le champ "%s" est obligatoire.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function optionalString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || '' === trim($value)) {
            return null;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requireBool(array $data, string $key): bool
    {
        $value = $data[$key] ?? null;
        if (!is_bool($value)) {
            throw new BadRequestHttpException(sprintf('Le champ "%s" doit être un booléen.', $key));
        }

        return $value;
    }
}
