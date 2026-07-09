<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\JournalEntry;
use App\Entity\User;
use App\Exception\EmptyJournalExportException;
use App\Exception\InvalidExportPeriodException;
use App\Repository\JournalEntryRepository;
use App\Service\JournalExportPdfService;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

final class JournalExportPdfServiceTest extends TestCase
{
    private JournalEntryRepository&Stub $journalEntryRepository;
    private Environment&Stub $twig;
    private JournalExportPdfService $service;

    protected function setUp(): void
    {
        $this->journalEntryRepository = $this->createStub(JournalEntryRepository::class);
        $this->twig = $this->createStub(Environment::class);
        $this->service = new JournalExportPdfService($this->journalEntryRepository, $this->twig);
    }

    public function testGenerateReturnsAPdfBinaryWhenEntriesExistOnThePeriod(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $entry = new JournalEntry($patient, $patient, 3, 4, '120/80', 'RAS');

        $from = new \DateTimeImmutable('2025-01-01');
        $to = new \DateTimeImmutable('2025-01-31');

        $capturedArgs = null;
        $this->journalEntryRepository->method('findByPatientAndPeriod')->willReturnCallback(
            function (User $p, \DateTimeImmutable $f, \DateTimeImmutable $t) use (&$capturedArgs, $entry): array {
                $capturedArgs = [$p, $f, $t];

                return [$entry];
            },
        );
        $this->twig->method('render')->willReturn('<html><body>Suivi</body></html>');

        $pdf = $this->service->generate($patient, $from, $to);

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertSame($patient, $capturedArgs[0]);
        self::assertEquals($from->setTime(0, 0), $capturedArgs[1]);
        self::assertEquals($to->setTime(23, 59, 59), $capturedArgs[2]);
    }

    public function testGenerateThrowsWhenNoEntryIsFoundOnThePeriod(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);

        $this->journalEntryRepository->method('findByPatientAndPeriod')->willReturn([]);

        $this->expectException(EmptyJournalExportException::class);

        $this->service->generate($patient, new \DateTimeImmutable('2025-01-01'), new \DateTimeImmutable('2025-01-31'));
    }

    public function testGenerateThrowsWhenFromIsAfterTo(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);

        $this->expectException(InvalidExportPeriodException::class);

        $this->service->generate($patient, new \DateTimeImmutable('2025-01-31'), new \DateTimeImmutable('2025-01-01'));
    }

    public function testGenerateThrowsWhenToIsInTheFuture(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);

        $this->expectException(InvalidExportPeriodException::class);

        $this->service->generate($patient, new \DateTimeImmutable('today'), new \DateTimeImmutable('tomorrow'));
    }

    public function testGenerateAllowsToDateEqualToToday(): void
    {
        $patient = $this->makeUser(1, User::ROLE_PATIENT);
        $entry = new JournalEntry($patient, $patient, 3, 4, '120/80');

        $this->journalEntryRepository->method('findByPatientAndPeriod')->willReturn([$entry]);
        $this->twig->method('render')->willReturn('<html><body>Suivi</body></html>');

        $pdf = $this->service->generate($patient, new \DateTimeImmutable('-1 day'), new \DateTimeImmutable('today'));

        self::assertStringStartsWith('%PDF-', $pdf);
    }

    private function makeUser(int $id, string $role): User
    {
        $user = new User(sprintf('user-%d@medlink.test', $id), 'Prenom', 'Nom');
        $user->setRoles([$role]);

        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }
}
