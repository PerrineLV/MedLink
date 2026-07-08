<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\JournalEntryCommentInput;
use App\Entity\JournalEntryComment;
use App\Repository\JournalEntryRepository;
use App\Service\JournalEntryCommentService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<JournalEntryCommentInput, JournalEntryComment>
 */
final class JournalEntryCommentProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly JournalEntryCommentService $journalEntryCommentService,
        private readonly JournalEntryRepository $journalEntryRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): JournalEntryComment
    {
        $journalEntry = $this->journalEntryRepository->find($data->journalEntryId);
        if (null === $journalEntry) {
            throw new NotFoundHttpException('Entrée de journal introuvable.');
        }

        return $this->journalEntryCommentService->create($journalEntry, $data->text);
    }
}
