<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\MedicationReferenceService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MedicationReferenceServiceTest extends TestCase
{
    private const FIXTURE_NAMES = [
        'DOLIPRANE 1000 mg, comprimé',
        'DOLIPRANE 500 mg, gélule',
        'DOLIPRANEXX 100 mg, sirop',
        'EFFERALGAN 500 mg, comprimé effervescent',
        'AMOXICILLINE ARROW 500 mg, gélule',
        'Éosine aqueuse 2 %, solution pour application locale',
    ];

    private const FIXTURE_EXTRACTED_AT = '2026-07-08';

    private string $fixturePath;

    protected function setUp(): void
    {
        $this->fixturePath = tempnam(sys_get_temp_dir(), 'medications').'.json';
        $fixture = ['extractedAt' => self::FIXTURE_EXTRACTED_AT, 'names' => self::FIXTURE_NAMES];
        file_put_contents($this->fixturePath, json_encode($fixture, JSON_UNESCAPED_UNICODE));
    }

    protected function tearDown(): void
    {
        unlink($this->fixturePath);
    }

    public function testSearchFindsMatchByPrefix(): void
    {
        $service = new MedicationReferenceService($this->fixturePath);

        $results = $service->search('doli');

        self::assertContains('DOLIPRANE 1000 mg, comprimé', $results);
        self::assertContains('DOLIPRANE 500 mg, gélule', $results);
    }

    public function testSearchIsCaseInsensitive(): void
    {
        $service = new MedicationReferenceService($this->fixturePath);

        self::assertNotEmpty($service->search('DoLi'));
    }

    public function testSearchIsAccentInsensitive(): void
    {
        $service = new MedicationReferenceService($this->fixturePath);

        self::assertContains('Éosine aqueuse 2 %, solution pour application locale', $service->search('eosine'));
    }

    public function testSearchPrioritizesPrefixMatchesOverContainsMatches(): void
    {
        $service = new MedicationReferenceService($this->fixturePath);

        $results = $service->search('amox');

        self::assertSame(['AMOXICILLINE ARROW 500 mg, gélule'], $results);
    }

    public function testSearchLimitsResultCount(): void
    {
        $service = new MedicationReferenceService($this->fixturePath);

        self::assertCount(2, $service->search('doli', 2));
    }

    public function testSearchReturnsEmptyArrayForBlankQuery(): void
    {
        $service = new MedicationReferenceService($this->fixturePath);

        self::assertSame([], $service->search('   '));
    }

    public function testSearchReturnsEmptyArrayWhenFileIsMissing(): void
    {
        $service = new MedicationReferenceService('/path/that/does/not/exist.json');

        self::assertSame([], $service->search('doli'));
    }

    public function testExtractedAtReturnsDateFromFile(): void
    {
        $service = new MedicationReferenceService($this->fixturePath);

        self::assertSame(self::FIXTURE_EXTRACTED_AT, $service->extractedAt());
    }

    public function testExtractedAtReturnsNullWhenFileIsMissing(): void
    {
        $service = new MedicationReferenceService('/path/that/does/not/exist.json');

        self::assertNull($service->extractedAt());
    }

    #[DataProvider('provideNamesAndExpectedDosages')]
    public function testSuggestDosageExtractsPlausibleDosage(string $name, ?string $expected): void
    {
        $service = new MedicationReferenceService($this->fixturePath);

        self::assertSame($expected, $service->suggestDosage($name));
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function provideNamesAndExpectedDosages(): iterable
    {
        yield 'simple mg dosage' => ['DOLIPRANE 1000 mg, comprimé', '1000 mg'];
        yield 'percentage' => ['Éosine aqueuse 2 %, solution pour application locale', '2 %'];
        yield 'thousands with spaces and U.I.' => ['A 313 50 000 U.I., capsule molle', '50 000 U.I.'];
        yield 'combination keeps first dosage only' => [
            'AMOXICILLINE ACIDE CLAVULANIQUE ALMUS 100 mg/12,5 mg par mL ENFANTS, poudre pour suspension buvable',
            '100 mg',
        ];
        yield 'no extractable dosage' => [
            'A.D.N. BOIRON, degré de dilution compris entre 4CH et 30CH ou entre 8DH et 60DH',
            null,
        ];
    }
}
