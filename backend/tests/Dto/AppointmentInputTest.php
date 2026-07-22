<?php

declare(strict_types=1);

namespace App\Tests\Dto;

use App\Dto\AppointmentInput;
use PHPUnit\Framework\TestCase;

final class AppointmentInputTest extends TestCase
{
    public function testConstructorSetsAllFields(): void
    {
        $input = new AppointmentInput(patientId: 1, scheduledAt: '2026-08-01T10:00:00+02:00', notes: 'Bilan trimestriel');

        self::assertSame(1, $input->patientId);
        self::assertSame('2026-08-01T10:00:00+02:00', $input->scheduledAt);
        self::assertSame('Bilan trimestriel', $input->notes);
    }

    public function testConstructorDefaultsNotesToNull(): void
    {
        $input = new AppointmentInput(patientId: 1, scheduledAt: '2026-08-01T10:00:00+02:00');

        self::assertNull($input->notes);
    }
}
