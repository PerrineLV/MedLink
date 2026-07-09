<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\LiaisonInvitation;
use App\Entity\PatientAidant;
use App\Entity\PatientSoignant;
use App\Service\LiaisonInvitationService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<null, LiaisonInvitation>
 */
final class LiaisonInvitationAcceptProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly LiaisonInvitationService $liaisonInvitationService,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LiaisonInvitation
    {
        $relation = $context['read_data'] ?? null;
        if (!$relation instanceof PatientAidant && !$relation instanceof PatientSoignant) {
            throw new NotFoundHttpException('Invitation introuvable.');
        }

        return $this->liaisonInvitationService->accept($relation);
    }
}
