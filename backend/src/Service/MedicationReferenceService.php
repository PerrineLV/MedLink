<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Autocomplétion sur un snapshot statique de la BDPM (ANSM), chargé une fois
 * en mémoire — fonctionnalité de confort, pas d'aide à la prescription
 * clinique (pas de vérification d'interactions/contre-indications).
 */
final class MedicationReferenceService
{
    private const DEFAULT_LIMIT = 10;

    /** @var list<string>|null */
    private ?array $names = null;
    private ?string $extractedAt = null;

    public function __construct(
        private readonly string $medicationsFilePath,
    ) {
    }

    /**
     * @return list<string>
     */
    public function search(string $query, int $limit = self::DEFAULT_LIMIT): array
    {
        $query = trim($query);
        if ('' === $query) {
            return [];
        }

        $needle = $this->normalize($query);
        $startsWith = [];
        $contains = [];

        foreach ($this->loadNames() as $name) {
            $normalizedName = $this->normalize($name);

            if (str_starts_with($normalizedName, $needle)) {
                $startsWith[] = $name;
            } elseif (str_contains($normalizedName, $needle)) {
                $contains[] = $name;
            }
        }

        return array_slice([...$startsWith, ...$contains], 0, $limit);
    }

    /**
     * Extrait un dosage plausible depuis la dénomination BDPM (qui n'a pas de
     * champ dosage séparé, ex. "DOLIPRANE 1000 mg, comprimé"). Best-effort :
     * sur une association (ex. "100 mg/12,5 mg par mL"), ne renvoie que la
     * première valeur trouvée — à confirmer/corriger par le soignant, jamais
     * imposé (champ toujours modifiable côté formulaire).
     */
    public function suggestDosage(string $name): ?string
    {
        // \d+(?:[\s.,]\d{3})*  gère le séparateur de milliers ("50 000") sans
        // avaler un numéro de code sans rapport ("A 313 50 000 U.I." ne doit
        // matcher que "50 000", pas "313 50 000") : un groupe de milliers doit
        // faire exactement 3 chiffres, sinon on retombe sur (?:[.,]\d+)? pour
        // les décimales ("12,5 mg"). Pas de \b final : "%"/"U.I." se
        // terminent par un caractère non-mot, donc \b n'y détecte jamais de
        // frontière suivi d'une virgule — on rejette juste une lettre collée
        // juste après (ex. éviter de matcher "mg" dans "mgroup").
        $pattern = '/\d+(?:[\s.,]\d{3})*(?:[.,]\d+)?\s*(?:mg|g|µg|mcg|mL|UI|U\.I\.|%)(?![a-zà-ÿ])/iu';

        if (1 === preg_match($pattern, $name, $matches)) {
            return trim(preg_replace('/\s+/', ' ', $matches[0]));
        }

        return null;
    }

    /**
     * Date (YYYY-MM-DD) de la dernière extraction BDPM ayant produit le
     * fichier chargé, ou null si absente/illisible — le fichier est
     * régénéré chaque semaine par .github/workflows/update-medications.yml.
     */
    public function extractedAt(): ?string
    {
        $this->load();

        return $this->extractedAt;
    }

    /**
     * @return list<string>
     */
    private function loadNames(): array
    {
        $this->load();

        return $this->names ?? [];
    }

    private function load(): void
    {
        if (null !== $this->names) {
            return;
        }

        if (!is_file($this->medicationsFilePath) || !is_readable($this->medicationsFilePath)) {
            $this->names = [];

            return;
        }

        $content = file_get_contents($this->medicationsFilePath);
        $decoded = false !== $content ? json_decode($content, true) : null;

        if (!is_array($decoded)) {
            $this->names = [];

            return;
        }

        $names = $decoded['names'] ?? null;
        $this->names = is_array($names) ? array_values(array_filter($names, 'is_string')) : [];

        $extractedAt = $decoded['extractedAt'] ?? null;
        $this->extractedAt = is_string($extractedAt) ? $extractedAt : null;
    }

    private function normalize(string $value): string
    {
        // iconv('UTF-8', 'ASCII//TRANSLIT', ...) raises a notice (escalated to
        // an exception by Symfony's error handler) on some BDPM entries with
        // characters it can't transliterate cleanly — decomposing accents and
        // stripping the combining marks avoids that entirely.
        $decomposed = \Normalizer::normalize($value, \Normalizer::FORM_D) ?: $value;
        $withoutDiacritics = preg_replace('/\p{Mn}/u', '', $decomposed) ?? $decomposed;

        return mb_strtolower($withoutDiacritics);
    }
}
