<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\JournalEntry;
use App\Entity\User;
use App\Repository\JournalEntryRepository;
use App\Security\VisiblePatientIds;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<JournalEntry>
 */
final class JournalEntryCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly JournalEntryRepository $journalEntryRepository,
        private readonly VisiblePatientIds $visiblePatientIds,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return list<JournalEntry>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $currentUser = $this->security->getUser();
        if (!$currentUser instanceof User) {
            return [];
        }

        $visiblePatientIds = $this->visiblePatientIds->forUser($currentUser);
        if ([] === $visiblePatientIds) {
            return [];
        }

        $patientFilter = $this->requestStack->getCurrentRequest()?->query->get('patient');
        if (null !== $patientFilter) {
            $patientId = (int) $patientFilter;
            if (!in_array($patientId, $visiblePatientIds, true)) {
                throw new AccessDeniedException("Vous n'avez pas accès au journal de ce patient.");
            }

            $visiblePatientIds = [$patientId];
        }

        return $this->journalEntryRepository->findByPatientIds($visiblePatientIds);
    }
}
