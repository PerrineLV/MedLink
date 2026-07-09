<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Appointment;
use App\Entity\User;
use App\Exception\InvalidAppointmentException;
use App\Repository\PatientSoignantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AppointmentService
{
    private const VALID_STATUSES = [
        Appointment::STATUS_PLANNED,
        Appointment::STATUS_CANCELLED,
        Appointment::STATUS_COMPLETED,
    ];

    private const NOTES_MAX_LENGTH = 1000;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PatientSoignantRepository $patientSoignantRepository,
        private readonly Security $security,
    ) {
    }

    public function create(User $patient, \DateTimeImmutable $scheduledAt, ?string $notes): Appointment
    {
        $soignant = $this->assertAuthorizedSoignant($patient);
        $this->assertValidNotes($notes);
        $this->assertNotInThePast($scheduledAt);

        $appointment = new Appointment($patient, $soignant, $scheduledAt, $notes);

        $this->entityManager->persist($appointment);
        $this->entityManager->flush();

        return $appointment;
    }

    public function update(
        Appointment $appointment,
        ?\DateTimeImmutable $scheduledAt,
        ?string $status,
        ?string $notes,
    ): Appointment {
        $this->assertAuthorizedSoignant($appointment->getPatient());

        if (null !== $scheduledAt) {
            $this->assertNotInThePast($scheduledAt);
            $appointment->setScheduledAt($scheduledAt);
        }

        if (null !== $status) {
            $this->assertValidStatus($status);
            $appointment->setStatus($status);
        }

        if (null !== $notes) {
            $this->assertValidNotes($notes);
            $appointment->setNotes($notes);
        }

        $this->entityManager->flush();

        return $appointment;
    }

    public function delete(Appointment $appointment): void
    {
        $this->assertAuthorizedSoignant($appointment->getPatient());

        $this->entityManager->remove($appointment);
        $this->entityManager->flush();
    }

    private function assertAuthorizedSoignant(User $patient): User
    {
        $soignant = $this->security->getUser();
        if (!$soignant instanceof User) {
            throw new AccessDeniedException('Utilisateur non authentifié.');
        }

        // Seul le soignant référent du patient peut créer/modifier/annuler un
        // rendez-vous, cf. TreatmentService::assertAuthorizedSoignant.
        if (!in_array(User::ROLE_SOIGNANT, $soignant->getRoles(), true)
            || !$this->patientSoignantRepository->hasActiveRelation($patient, $soignant)) {
            throw new AccessDeniedException("Vous n'êtes pas autorisé à gérer un rendez-vous pour ce patient.");
        }

        return $soignant;
    }

    private function assertNotInThePast(\DateTimeImmutable $scheduledAt): void
    {
        if ($scheduledAt < new \DateTimeImmutable()) {
            throw new InvalidAppointmentException('La date du rendez-vous ne peut pas être dans le passé.');
        }
    }

    private function assertValidStatus(string $status): void
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new InvalidAppointmentException('Le statut doit être "planned", "cancelled" ou "completed".');
        }
    }

    private function assertValidNotes(?string $notes): void
    {
        if (null !== $notes && mb_strlen($notes) > self::NOTES_MAX_LENGTH) {
            throw new InvalidAppointmentException(sprintf('Les notes ne peuvent pas dépasser %d caractères.', self::NOTES_MAX_LENGTH));
        }
    }
}
