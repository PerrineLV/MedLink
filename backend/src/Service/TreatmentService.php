<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Treatment;
use App\Entity\TreatmentSchedule;
use App\Entity\User;
use App\Exception\InvalidTreatmentException;
use App\Repository\PatientSoignantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class TreatmentService
{
    private const NAME_MAX_LENGTH = 255;
    private const DOSAGE_MAX_LENGTH = 100;
    private const CUSTOM_LABEL_MAX_LENGTH = 100;

    private const VALID_MOMENTS = [
        TreatmentSchedule::MOMENT_MORNING,
        TreatmentSchedule::MOMENT_NOON,
        TreatmentSchedule::MOMENT_EVENING,
        TreatmentSchedule::MOMENT_CUSTOM,
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PatientSoignantRepository $patientSoignantRepository,
        private readonly Security $security,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $schedules
     */
    public function create(User $patient, string $name, string $dosage, array $schedules): Treatment
    {
        $this->assertValidNameAndDosage($name, $dosage);
        $this->assertValidSchedules($schedules);

        $soignant = $this->assertAuthorizedSoignant($patient);

        $treatment = new Treatment($patient, $name, $dosage, $soignant);

        $this->entityManager->persist($treatment);
        $this->addSchedules($treatment, $schedules);
        $this->entityManager->flush();

        return $treatment;
    }

    /**
     * @param list<array<string, mixed>>|null $schedules
     */
    public function update(
        Treatment $treatment,
        ?string $name,
        ?string $dosage,
        ?array $schedules,
        ?bool $active,
    ): Treatment {
        $this->assertAuthorizedSoignant($treatment->getPatient());

        $this->assertValidNameAndDosage($name ?? $treatment->getName(), $dosage ?? $treatment->getDosage());
        if (null !== $schedules) {
            $this->assertValidSchedules($schedules);
        }

        if (null !== $name) {
            $treatment->setName($name);
        }

        if (null !== $dosage) {
            $treatment->setDosage($dosage);
        }

        if (null !== $schedules) {
            // Remplacement complet de la liste d'horaires : plus simple qu'un
            // diff fin, acceptable tant qu'aucun frontend n'appelle ce PATCH.
            foreach ($treatment->getSchedules() as $schedule) {
                $this->entityManager->remove($schedule);
            }
            $treatment->clearSchedules();
            $this->addSchedules($treatment, $schedules);
        }

        if (null !== $active) {
            $treatment->setActive($active);
        }

        $this->entityManager->flush();

        return $treatment;
    }

    /**
     * @param list<array<string, mixed>> $schedules
     */
    private function addSchedules(Treatment $treatment, array $schedules): void
    {
        foreach ($schedules as $item) {
            $moment = (string) $item['moment'];
            $customLabel = TreatmentSchedule::MOMENT_CUSTOM === $moment ? trim((string) ($item['label'] ?? '')) : null;

            $schedule = new TreatmentSchedule($treatment, $moment, $customLabel);
            $treatment->addSchedule($schedule);
            $this->entityManager->persist($schedule);
        }
    }

    private function assertAuthorizedSoignant(User $patient): User
    {
        $soignant = $this->security->getUser();
        if (!$soignant instanceof User) {
            throw new AccessDeniedException('Utilisateur non authentifié.');
        }

        // La prescription est réservée au soignant référent du patient : ni le
        // patient ni son aidant ne doivent pouvoir créer/modifier un
        // traitement (contrairement à JournalEntryService::create), donc on
        // vérifie directement la relation plutôt que d'utiliser UserVoter.
        if (!in_array(User::ROLE_SOIGNANT, $soignant->getRoles(), true)
            || !$this->patientSoignantRepository->hasActiveRelation($patient, $soignant)) {
            throw new AccessDeniedException("Vous n'êtes pas autorisé à prescrire un traitement pour ce patient.");
        }

        return $soignant;
    }

    private function assertValidNameAndDosage(string $name, string $dosage): void
    {
        if ('' === trim($name)) {
            throw new InvalidTreatmentException('Le nom du traitement ne peut pas être vide.');
        }

        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw new InvalidTreatmentException(sprintf('Le nom du traitement ne peut pas dépasser %d caractères.', self::NAME_MAX_LENGTH));
        }

        if ('' === trim($dosage)) {
            throw new InvalidTreatmentException('Le dosage ne peut pas être vide.');
        }

        if (mb_strlen($dosage) > self::DOSAGE_MAX_LENGTH) {
            throw new InvalidTreatmentException(sprintf('Le dosage ne peut pas dépasser %d caractères.', self::DOSAGE_MAX_LENGTH));
        }
    }

    /**
     * @param list<array<string, mixed>> $schedules
     */
    private function assertValidSchedules(array $schedules): void
    {
        if ([] === $schedules) {
            throw new InvalidTreatmentException('Au moins un horaire est requis.');
        }

        $nonCustomMoments = [];

        foreach ($schedules as $item) {
            $moment = $item['moment'] ?? null;

            if (!is_string($moment) || !in_array($moment, self::VALID_MOMENTS, true)) {
                throw new InvalidTreatmentException('Le moment doit être "morning", "noon", "evening" ou "custom".');
            }

            if (TreatmentSchedule::MOMENT_CUSTOM === $moment) {
                $label = trim((string) ($item['label'] ?? ''));

                if ('' === $label) {
                    throw new InvalidTreatmentException('Un libellé est requis pour un horaire personnalisé.');
                }

                if (mb_strlen($label) > self::CUSTOM_LABEL_MAX_LENGTH) {
                    throw new InvalidTreatmentException(sprintf('Le libellé personnalisé ne peut pas dépasser %d caractères.', self::CUSTOM_LABEL_MAX_LENGTH));
                }
            } else {
                $nonCustomMoments[] = $moment;
            }
        }

        if (count($nonCustomMoments) !== count(array_unique($nonCustomMoments))) {
            throw new InvalidTreatmentException('"Matin", "Midi" et "Soir" ne peuvent apparaître qu\'une fois chacun pour un même traitement.');
        }
    }
}
