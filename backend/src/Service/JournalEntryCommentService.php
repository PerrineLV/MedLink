<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\JournalEntry;
use App\Entity\JournalEntryComment;
use App\Entity\User;
use App\Exception\InvalidJournalEntryCommentException;
use App\Repository\PatientSoignantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class JournalEntryCommentService
{
    private const TEXT_MAX_LENGTH = 1000;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PatientSoignantRepository $patientSoignantRepository,
        private readonly Security $security,
    ) {
    }

    public function create(JournalEntry $journalEntry, string $text): JournalEntryComment
    {
        $this->assertValid($text);

        $author = $this->security->getUser();
        if (!$author instanceof User) {
            throw new AccessDeniedException('Utilisateur non authentifié.');
        }

        // Commenter une entrée est réservé au soignant référent du patient :
        // contrairement à JournalEntryService::create (PATIENT_MANAGE), le
        // patient et son aidant ne doivent pas pouvoir laisser ce type de
        // commentaire sur leur propre journal.
        if (!in_array(User::ROLE_SOIGNANT, $author->getRoles(), true)
            || !$this->patientSoignantRepository->hasActiveRelation($journalEntry->getPatient(), $author)) {
            throw new AccessDeniedException("Vous n'êtes pas autorisé à commenter cette entrée.");
        }

        $comment = new JournalEntryComment($journalEntry, $author, $text);

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        return $comment;
    }

    private function assertValid(string $text): void
    {
        if ('' === trim($text)) {
            throw new InvalidJournalEntryCommentException('Le commentaire ne peut pas être vide.');
        }

        if (mb_strlen($text) > self::TEXT_MAX_LENGTH) {
            throw new InvalidJournalEntryCommentException(sprintf('Le commentaire ne peut pas dépasser %d caractères.', self::TEXT_MAX_LENGTH));
        }
    }
}
