<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Treatment;
use App\Entity\User;
use App\Repository\TreatmentRepository;
use App\Security\VisiblePatientIds;
use App\Service\TreatmentIntakeService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<Treatment>
 */
final class TreatmentCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly TreatmentRepository $treatmentRepository,
        private readonly TreatmentIntakeService $treatmentIntakeService,
        private readonly VisiblePatientIds $visiblePatientIds,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return list<Treatment>
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

        $request = $this->requestStack->getCurrentRequest();

        $patientFilter = $request?->query->get('patient');
        if (null !== $patientFilter) {
            $patientId = (int) $patientFilter;
            if (!in_array($patientId, $visiblePatientIds, true)) {
                throw new AccessDeniedException("Vous n'avez pas accès aux traitements de ce patient.");
            }

            $visiblePatientIds = [$patientId];
        }

        $dateFilter = $request?->query->get('date');
        $date = null !== $dateFilter
            ? new \DateTimeImmutable($dateFilter)
            : new \DateTimeImmutable('today');

        $treatments = $this->treatmentRepository->findActiveByPatientIds($visiblePatientIds);

        foreach ($treatments as $treatment) {
            foreach ($treatment->getSchedules() as $schedule) {
                $schedule->setTodayIntake($this->treatmentIntakeService->findOrCreateForDate($schedule, $date));
            }
        }

        return $treatments;
    }
}
