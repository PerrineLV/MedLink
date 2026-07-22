<?php

declare(strict_types=1);

namespace App\Tests\Exception;

use App\Exception\EmptyJournalExportException;
use App\Exception\InvalidAppointmentException;
use App\Exception\InvalidExportPeriodException;
use App\Exception\InvalidJournalEntryCommentException;
use App\Exception\InvalidJournalEntryException;
use App\Exception\InvalidMessageException;
use App\Exception\InvalidTreatmentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DomainExceptionsTest extends TestCase
{
    /**
     * @param class-string<\DomainException> $exceptionClass
     */
    #[DataProvider('provideDomainExceptionClasses')]
    public function testExceptionExtendsDomainExceptionAndKeepsItsMessage(string $exceptionClass): void
    {
        $exception = new $exceptionClass('Message métier.');

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertSame('Message métier.', $exception->getMessage());
    }

    /**
     * @return iterable<string, array{class-string<\DomainException>}>
     */
    public static function provideDomainExceptionClasses(): iterable
    {
        yield 'empty journal export' => [EmptyJournalExportException::class];
        yield 'invalid appointment' => [InvalidAppointmentException::class];
        yield 'invalid export period' => [InvalidExportPeriodException::class];
        yield 'invalid journal entry comment' => [InvalidJournalEntryCommentException::class];
        yield 'invalid journal entry' => [InvalidJournalEntryException::class];
        yield 'invalid message' => [InvalidMessageException::class];
        yield 'invalid treatment' => [InvalidTreatmentException::class];
    }
}
