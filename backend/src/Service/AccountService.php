<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\JournalEntryRepository;
use App\Repository\PatientAidantRepository;
use App\Repository\PatientSoignantRepository;
use App\Repository\TreatmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AccountService
{
    private const PASSWORD_MIN_LENGTH = 8;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JournalEntryRepository $journalEntryRepository,
        private readonly TreatmentRepository $treatmentRepository,
        private readonly PatientAidantRepository $patientAidantRepository,
        private readonly PatientSoignantRepository $patientSoignantRepository,
    ) {
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            throw new AccessDeniedHttpException('Mot de passe actuel incorrect.');
        }

        $this->assertPasswordIsRobust($newPassword);

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $this->entityManager->flush();
    }

    /**
     * @return array<string, mixed>
     */
    public function exportData(User $user): array
    {
        $account = [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'roles' => $user->getRoles(),
            'title' => $user->getTitle(),
            'createdAt' => $user->getCreatedAt()->format(\DATE_ATOM),
        ];

        if (in_array(User::ROLE_PATIENT, $user->getRoles(), true)) {
            return [
                'account' => $account,
                'journalEntries' => array_map(
                    static fn ($entry): array => [
                        'id' => $entry->getId(),
                        'mood' => $entry->getMood(),
                        'painLevel' => $entry->getPainLevel(),
                        'bloodPressure' => $entry->getBloodPressure(),
                        'note' => $entry->getNote(),
                        'enteredByCaregiver' => $entry->isEnteredByCaregiver(),
                        'createdAt' => $entry->getCreatedAt()->format(\DATE_ATOM),
                    ],
                    $this->journalEntryRepository->findByPatientIds([$user->getId()]),
                ),
                'treatments' => array_map(
                    static fn ($treatment): array => [
                        'id' => $treatment->getId(),
                        'name' => $treatment->getName(),
                        'dosage' => $treatment->getDosage(),
                        'active' => $treatment->isActive(),
                        'createdAt' => $treatment->getCreatedAt()->format(\DATE_ATOM),
                        'schedules' => array_map(
                            static fn ($schedule): array => [
                                'moment' => $schedule->getMoment(),
                                'customLabel' => $schedule->getCustomLabel(),
                            ],
                            $treatment->getSchedules()->toArray(),
                        ),
                    ],
                    $this->treatmentRepository->findByPatientIds([$user->getId()]),
                ),
            ];
        }

        $liaisons = [];

        if (in_array(User::ROLE_AIDANT, $user->getRoles(), true)) {
            foreach ($this->patientAidantRepository->findForAidant($user) as $relation) {
                $liaisons[] = [
                    'patientId' => $relation->getPatient()->getId(),
                    'patientFirstName' => $relation->getPatient()->getFirstName(),
                    'patientLastName' => $relation->getPatient()->getLastName(),
                    'active' => $relation->isActive(),
                    'createdAt' => $relation->getCreatedAt()->format(\DATE_ATOM),
                    'revokedAt' => $relation->getRevokedAt()?->format(\DATE_ATOM),
                ];
            }
        }

        if (in_array(User::ROLE_SOIGNANT, $user->getRoles(), true)) {
            foreach ($this->patientSoignantRepository->findForSoignant($user) as $relation) {
                $liaisons[] = [
                    'patientId' => $relation->getPatient()->getId(),
                    'patientFirstName' => $relation->getPatient()->getFirstName(),
                    'patientLastName' => $relation->getPatient()->getLastName(),
                    'active' => $relation->isActive(),
                    'createdAt' => $relation->getCreatedAt()->format(\DATE_ATOM),
                    'revokedAt' => $relation->getRevokedAt()?->format(\DATE_ATOM),
                ];
            }
        }

        return [
            'account' => $account,
            'liaisons' => $liaisons,
        ];
    }

    public function deleteAccount(User $user, string $password): void
    {
        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            throw new AccessDeniedHttpException('Mot de passe incorrect.');
        }

        $originalEmail = $user->getEmail();

        $user->setEmail(sprintf('utilisateur-supprime-%d@medlink.invalid', $user->getId()));
        $user->setFirstName('Utilisateur');
        $user->setLastName('Supprimé');
        $user->setTitle(null);
        $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
        $user->setDeletedAt(new \DateTimeImmutable());

        $refreshTokenRepository = $this->entityManager->getRepository(RefreshToken::class);
        foreach ($refreshTokenRepository->findBy(['username' => $originalEmail]) as $refreshToken) {
            $this->entityManager->remove($refreshToken);
        }

        $this->entityManager->flush();
    }

    private function assertPasswordIsRobust(string $password): void
    {
        if (mb_strlen($password) < self::PASSWORD_MIN_LENGTH
            || 1 !== preg_match('/\d/', $password)
            || 1 !== preg_match('/[A-Za-z]/', $password)
        ) {
            throw new BadRequestHttpException(sprintf('Le mot de passe doit contenir au moins %d caractères, dont un chiffre et une lettre.', self::PASSWORD_MIN_LENGTH));
        }
    }
}
