<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\JournalEntry;
use App\Entity\User;
use App\Exception\InvalidJournalEntryException;
use App\Security\Voter\UserVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class JournalEntryService
{
    private const MOOD_MIN = 1;
    private const MOOD_MAX = 5;
    private const PAIN_LEVEL_MIN = 0;
    private const PAIN_LEVEL_MAX = 10;
    private const NOTE_MAX_LENGTH = 1000;
    private const BLOOD_PRESSURE_PATTERN = '/^\d{1,3}\/\d{1,3}$/';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function create(
        User $patient,
        int $mood,
        int $painLevel,
        string $bloodPressure,
        ?string $note,
    ): JournalEntry {
        $this->assertValid($mood, $painLevel, $bloodPressure, $note);

        $author = $this->security->getUser();
        if (!$author instanceof User) {
            throw new AccessDeniedException('Utilisateur non authentifié.');
        }

        if (!$this->security->isGranted(UserVoter::MANAGE, $patient)) {
            throw new AccessDeniedException("Vous n'êtes pas autorisé à saisir une entrée de journal pour ce patient.");
        }

        $entry = new JournalEntry($patient, $author, $mood, $painLevel, $bloodPressure, $note);

        $this->entityManager->persist($entry);
        $this->entityManager->flush();

        return $entry;
    }

    private function assertValid(int $mood, int $painLevel, string $bloodPressure, ?string $note): void
    {
        if ($mood < self::MOOD_MIN || $mood > self::MOOD_MAX) {
            throw new InvalidJournalEntryException(sprintf("L'humeur doit être comprise entre %d et %d.", self::MOOD_MIN, self::MOOD_MAX));
        }

        if ($painLevel < self::PAIN_LEVEL_MIN || $painLevel > self::PAIN_LEVEL_MAX) {
            throw new InvalidJournalEntryException(sprintf('Le niveau de douleur doit être compris entre %d et %d.', self::PAIN_LEVEL_MIN, self::PAIN_LEVEL_MAX));
        }

        if (1 !== preg_match(self::BLOOD_PRESSURE_PATTERN, $bloodPressure)) {
            throw new InvalidJournalEntryException('La tension doit être au format "120/80".');
        }

        if (null !== $note && mb_strlen($note) > self::NOTE_MAX_LENGTH) {
            throw new InvalidJournalEntryException(sprintf('La note ne peut pas dépasser %d caractères.', self::NOTE_MAX_LENGTH));
        }
    }
}
