<?php

declare(strict_types=1);

namespace App\Tests\Dto;

use App\Dto\TreatmentInput;
use PHPUnit\Framework\TestCase;

final class TreatmentInputTest extends TestCase
{
    public function testConstructorSetsAllFields(): void
    {
        $schedules = [['moment' => 'morning']];

        $input = new TreatmentInput(patientId: 1, name: 'Bisoprolol', dosage: '5 mg', schedules: $schedules);

        self::assertSame(1, $input->patientId);
        self::assertSame('Bisoprolol', $input->name);
        self::assertSame('5 mg', $input->dosage);
        self::assertSame($schedules, $input->schedules);
    }
}
