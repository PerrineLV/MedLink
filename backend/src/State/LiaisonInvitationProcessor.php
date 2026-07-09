<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\LiaisonInvitation;
use App\Dto\LiaisonInvitationInput;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\LiaisonInvitationService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<LiaisonInvitationInput, LiaisonInvitation>
 */
final class LiaisonInvitationProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly LiaisonInvitationService $liaisonInvitationService,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LiaisonInvitation
    {
        $invitee = $this->userRepository->findOneBy(['email' => $data->email]);

        // Un message générique, qu'il s'agisse d'un email totalement inconnu,
        // d'un compte ni aidant ni soignant, ou d'un compte patient (y compris
        // le patient lui-même, ou un compte cumulant ROLE_PATIENT avec un
        // autre rôle) : on évite ainsi de laisser deviner l'existence ou le
        // rôle d'un compte, et un patient ne peut jamais devenir le lien
        // aidant/soignant d'un autre patient.
        if (null === $invitee
            || in_array(User::ROLE_PATIENT, $invitee->getRoles(), true)
            || (!in_array(User::ROLE_AIDANT, $invitee->getRoles(), true)
                && !in_array(User::ROLE_SOIGNANT, $invitee->getRoles(), true))) {
            throw new NotFoundHttpException('Aucun aidant ou soignant trouvé avec cet email.');
        }

        return $this->liaisonInvitationService->invite($invitee);
    }
}
