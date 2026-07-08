#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Régénère backend/resources/medications.json à partir du "Fichier des
 * spécialités" (CIS_bdpm) de la BDPM, publié par l'ANSM en licence ouverte :
 * https://base-donnees-publique.medicaments.gouv.fr/.
 *
 * Usage : php backend/bin/update-medications.php
 * Appelé chaque semaine par .github/workflows/update-medications.yml (ML-51),
 * qui ouvre une pull request si le fichier a changé.
 */
const SOURCE_URL = 'https://base-donnees-publique.medicaments.gouv.fr/download/file/CIS_bdpm.txt';
const DEST_PATH = __DIR__.'/../resources/medications.json';

$content = file_get_contents(SOURCE_URL);
if (false === $content) {
    fwrite(STDERR, 'Impossible de télécharger '.SOURCE_URL."\n");
    exit(1);
}

$content = iconv('ISO-8859-1', 'UTF-8//TRANSLIT', $content);
if (false === $content) {
    fwrite(STDERR, "Échec de la conversion Latin-1 vers UTF-8\n");
    exit(1);
}

$names = [];
foreach (explode("\n", $content) as $line) {
    $line = rtrim($line, "\r");
    if ('' === trim($line)) {
        continue;
    }

    $columns = explode("\t", $line);
    $name = trim($columns[1] ?? '');

    if (mb_strlen($name) < 2) {
        continue;
    }

    $names[$name] = true;
}

$names = array_keys($names);
sort($names, SORT_STRING | SORT_FLAG_CASE);

$data = [
    'extractedAt' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d'),
    'names' => $names,
];

// JSON_PRETTY_PRINT (un nom par ligne) : le diff de la PR hebdomadaire reste
// lisible (médicaments ajoutés/retirés), au prix d'un fichier un peu plus gros.
$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if (false === $json) {
    fwrite(STDERR, 'Échec de l\'encodage JSON : '.json_last_error_msg()."\n");
    exit(1);
}

file_put_contents(DEST_PATH, $json."\n");

fwrite(STDOUT, sprintf("%d noms écrits dans %s\n", count($names), DEST_PATH));
