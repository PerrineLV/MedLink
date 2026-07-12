<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\JournalEntryInput;
use App\Entity\JournalEntry;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\JournalEntryService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProcessorInterface<JournalEntryInput, JournalEntry>
 */
final class JournalEntryProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly JournalEntryService $journalEntryService,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): JournalEntry
    {
        if (null === $data->patientId) {
            // Front-end sends patientId: null for an aidant with no attached
            // patient (cf. ML-85); without this guard, an int-typed
            // JournalEntryInput constructor throws a raw TypeError on a null
            // value, surfacing as an uncaught 500.
            throw new AccessDeniedException("Vous n'avez pas de patient rattaché pour saisir une entrée de journal.");
        }

        $patient = $this->userRepository->find($data->patientId);
        if (!$patient instanceof User || !in_array(User::ROLE_PATIENT, $patient->getRoles(), true)) {
            throw new NotFoundHttpException('Patient introuvable.');
        }

        return $this->journalEntryService->create(
            $patient,
            $data->mood,
            $data->painLevel,
            $data->bloodPressure,
            $data->note,
        );
    }
}
