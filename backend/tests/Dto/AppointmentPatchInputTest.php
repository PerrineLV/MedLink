<?php

declare(strict_types=1);

namespace App\Tests\Dto;

use App\Dto\AppointmentPatchInput;
use PHPUnit\Framework\TestCase;

final class AppointmentPatchInputTest extends TestCase
{
    public function testConstructorSetsAllFields(): void
    {
        $input = new AppointmentPatchInput(scheduledAt: '2026-08-01T10:00:00+02:00', status: 'cancelled', notes: 'Reporté');

        self::assertSame('2026-08-01T10:00:00+02:00', $input->scheduledAt);
        self::assertSame('cancelled', $input->status);
        self::assertSame('Reporté', $input->notes);
    }

    public function testConstructorDefaultsAllFieldsToNull(): void
    {
        $input = new AppointmentPatchInput();

        self::assertNull($input->scheduledAt);
        self::assertNull($input->status);
        self::assertNull($input->notes);
    }
}
