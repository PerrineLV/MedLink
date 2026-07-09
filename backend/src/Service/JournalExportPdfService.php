<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Exception\EmptyJournalExportException;
use App\Exception\InvalidExportPeriodException;
use App\Repository\JournalEntryRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

final class JournalExportPdfService
{
    public function __construct(
        private readonly JournalEntryRepository $journalEntryRepository,
        private readonly Environment $twig,
    ) {
    }

    public function generate(User $patient, \DateTimeImmutable $from, \DateTimeImmutable $to): string
    {
        $this->assertValidPeriod($from, $to);

        $entries = $this->journalEntryRepository->findByPatientAndPeriod(
            $patient,
            $from->setTime(0, 0),
            $to->setTime(23, 59, 59),
        );

        if ([] === $entries) {
            throw new EmptyJournalExportException("Aucune entrée de journal n'a été trouvée sur cette période.");
        }

        $html = $this->twig->render('export/journal_pdf.html.twig', [
            'patient' => $patient,
            'from' => $from,
            'to' => $to,
            'entries' => $entries,
            'generatedAt' => new \DateTimeImmutable(),
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }

    private function assertValidPeriod(\DateTimeImmutable $from, \DateTimeImmutable $to): void
    {
        if ($from > $to) {
            throw new InvalidExportPeriodException('La date de début doit être antérieure ou égale à la date de fin.');
        }

        $today = new \DateTimeImmutable('today');
        if ($to->setTime(0, 0) > $today) {
            throw new InvalidExportPeriodException('La date de fin ne peut pas être dans le futur.');
        }
    }
}
