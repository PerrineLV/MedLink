<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Treatment;
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
    private const SCHEDULED_TIME_PATTERN = '/^([01]\d|2[0-3]):[0-5]\d$/';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PatientSoignantRepository $patientSoignantRepository,
        private readonly Security $security,
    ) {
    }

    public function create(User $patient, string $name, string $dosage, string $scheduledTime): Treatment
    {
        $this->assertValid($name, $dosage, $scheduledTime);

        $soignant = $this->assertAuthorizedSoignant($patient);

        $treatment = new Treatment($patient, $name, $dosage, $scheduledTime, $soignant);

        $this->entityManager->persist($treatment);
        $this->entityManager->flush();

        return $treatment;
    }

    public function update(
        Treatment $treatment,
        ?string $name,
        ?string $dosage,
        ?string $scheduledTime,
        ?bool $active,
    ): Treatment {
        $this->assertAuthorizedSoignant($treatment->getPatient());

        $this->assertValid(
            $name ?? $treatment->getName(),
            $dosage ?? $treatment->getDosage(),
            $scheduledTime ?? $treatment->getScheduledTime(),
        );

        if (null !== $name) {
            $treatment->setName($name);
        }

        if (null !== $dosage) {
            $treatment->setDosage($dosage);
        }

        if (null !== $scheduledTime) {
            $treatment->setScheduledTime($scheduledTime);
        }

        if (null !== $active) {
            $treatment->setActive($active);
        }

        $this->entityManager->flush();

        return $treatment;
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

    private function assertValid(string $name, string $dosage, string $scheduledTime): void
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

        if (1 !== preg_match(self::SCHEDULED_TIME_PATTERN, $scheduledTime)) {
            throw new InvalidTreatmentException('L\'heure prévue doit être au format "HH:MM".');
        }
    }
}
