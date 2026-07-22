<?php

declare(strict_types=1);

namespace App\Tests\Dto;

use App\Dto\TreatmentPatchInput;
use PHPUnit\Framework\TestCase;

final class TreatmentPatchInputTest extends TestCase
{
    public function testConstructorSetsAllFields(): void
    {
        $schedules = [['moment' => 'evening']];

        $input = new TreatmentPatchInput(name: 'Ramipril', dosage: '10 mg', schedules: $schedules, active: false);

        self::assertSame('Ramipril', $input->name);
        self::assertSame('10 mg', $input->dosage);
        self::assertSame($schedules, $input->schedules);
        self::assertFalse($input->active);
    }

    public function testConstructorDefaultsAllFieldsToNull(): void
    {
        $input = new TreatmentPatchInput();

        self::assertNull($input->name);
        self::assertNull($input->dosage);
        self::assertNull($input->schedules);
        self::assertNull($input->active);
    }
}
