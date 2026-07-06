<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\JournalEntry;
use App\Entity\User;
use App\Repository\JournalEntryRepository;
use App\Repository\PatientAidantRepository;
use App\Repository\PatientSoignantRepository;
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
        private readonly PatientAidantRepository $patientAidantRepository,
        private readonly PatientSoignantRepository $patientSoignantRepository,
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

        $visiblePatientIds = $this->visiblePatientIds($currentUser);
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

    /**
     * @return list<int>
     */
    private function visiblePatientIds(User $user): array
    {
        if (in_array(User::ROLE_PATIENT, $user->getRoles(), true)) {
            return [$user->getId()];
        }

        if (in_array(User::ROLE_AIDANT, $user->getRoles(), true)) {
            return $this->patientAidantRepository->findActivePatientIdsForAidant($user);
        }

        if (in_array(User::ROLE_SOIGNANT, $user->getRoles(), true)) {
            return $this->patientSoignantRepository->findActivePatientIdsForSoignant($user);
        }

        return [];
    }
}
