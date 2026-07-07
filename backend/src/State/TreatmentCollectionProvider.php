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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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

        $request = $this->requestStack->getCurrentRequest();

        $patientFilter = $request?->query->get('patient');
        if (null === $patientFilter) {
            throw new BadRequestHttpException('Le paramètre "patient" est obligatoire.');
        }

        $patientId = (int) $patientFilter;
        $visiblePatientIds = $this->visiblePatientIds->forUser($currentUser);
        if (!in_array($patientId, $visiblePatientIds, true)) {
            throw new AccessDeniedException("Vous n'avez pas accès aux traitements de ce patient.");
        }

        $dateFilter = $request->query->get('date');
        $date = null !== $dateFilter
            ? new \DateTimeImmutable($dateFilter)
            : new \DateTimeImmutable('today');

        $treatments = $this->treatmentRepository->findActiveByPatient($patientId);

        foreach ($treatments as $treatment) {
            $treatment->setTodayIntake($this->treatmentIntakeService->findOrCreateForDate($treatment, $date));
        }

        return $treatments;
    }
}
