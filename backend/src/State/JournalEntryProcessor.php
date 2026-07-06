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
