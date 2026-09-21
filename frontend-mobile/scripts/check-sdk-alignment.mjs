#!/usr/bin/env node
// ML-171 — Vérifie qu'aucun paquet géré par le SDK Expo n'est installé
// au-dessus de la version que ce SDK attend.
//
// Remplace `expo-doctor` comme étape CI BLOQUANTE. expo-doctor exige la toute
// dernière version publiée compatible avec le SDK, ce qui échoue dès qu'Expo
// publie un correctif que le projet n'a pas encore absorbé — sans qu'aucun
// auteur de PR n'en soit responsable. Constaté le 21/09/2026 : `npx
// expo-doctor` échoue (code 1) sur un projet par ailleurs correctement
// aligné, à cause de 3 paquets en retard d'un seul patch Expo. Un check requis
// ne doit pouvoir échouer que pour une raison que l'auteur de la PR contrôle.
//
// Ce script applique EXACTEMENT la même politique que les bornes de
// .github/dependabot.yml (voir le commentaire là-bas) : un paquet géré par le
// SDK ne doit jamais dépasser le MINEUR suivant celui déclaré par
// `bundledNativeModules.json`. En dessous ou dans ce mineur : accepté, y
// compris un patch antérieur au tout dernier publié — précisément ce
// qu'expo-doctor refuse et que notre politique tolère délibérément (un
// correctif de sécurité arrivé via Dependabot doit pouvoir être installé sans
// attendre qu'Expo republie le paquet concerné le même jour).
//
// Les bornes Dependabot empêchent la PROPOSITION d'une version désalignée.
// Ce script attrape l'ERREUR HUMAINE si elle passe quand même (bump manuel,
// résolution transitive, `npm install <paquet>@latest`...).
//
// expo-doctor garde toute sa valeur pour ses 20 autres contrôles : voir
// .github/workflows/expo-doctor.yml, hebdomadaire, non bloquant.

import { readFileSync, existsSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const rootDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");

function readJson(relativePath) {
  const absolutePath = path.join(rootDir, relativePath);
  if (!existsSync(absolutePath)) {
    return null;
  }
  return JSON.parse(readFileSync(absolutePath, "utf8"));
}

// Un fichier de référence absent ne doit jamais se lire comme "rien à
// signaler" : voir deploy/lib/archive-checks.sh (ML-143) pour le même
// principe appliqué ailleurs sur ce projet — un contrôle qui ne trouve pas sa
// donnée de référence échoue bruyamment, il ne rend jamais 0 en silence.
const manifest = readJson("node_modules/expo/bundledNativeModules.json");
if (!manifest) {
  console.error(
    "ERREUR : node_modules/expo/bundledNativeModules.json introuvable. " +
      "Le SDK Expo n'est pas installé (npm ci a-t-il tourné ?) — impossible de " +
      "vérifier l'alignement.",
  );
  process.exit(1);
}

const pkg = readJson("package.json");
if (!pkg) {
  console.error("ERREUR : package.json introuvable.");
  process.exit(1);
}

// Extrait "major.minor" d'un épinglage SDK, qu'il soit préfixé (~57.0.19,
// ^15.0.2) ou exact (2.2.0, 19.2.3).
function sdkMajorMinor(pin) {
  const numeric = pin.replace(/^[~^]/, "");
  const [major, minor] = numeric.split(".").map(Number);
  return { major, minor };
}

function installedVersion(packageName) {
  const installed = readJson(path.join("node_modules", packageName, "package.json"));
  return installed ? installed.version : null;
}

const dependencyNames = new Set([
  ...Object.keys(pkg.dependencies ?? {}),
  ...Object.keys(pkg.devDependencies ?? {}),
]);

const managedByExpo = Object.keys(manifest).filter((name) => dependencyNames.has(name));

const expoVersion = readJson("node_modules/expo/package.json")?.version ?? "?";
console.log(`SDK Expo installé : ${expoVersion}. Paquets gérés par ce SDK et présents dans ce projet : ${managedByExpo.length}`);

const failures = [];

for (const name of managedByExpo) {
  const pin = manifest[name];
  const installed = installedVersion(name);

  if (!installed) {
    failures.push(`${name} : déclaré dans package.json mais absent de node_modules (npm ci incomplet ?)`);
    continue;
  }

  const sdk = sdkMajorMinor(pin);
  const got = sdkMajorMinor(installed);

  const aboveMinor = got.major > sdk.major || (got.major === sdk.major && got.minor > sdk.minor);

  if (aboveMinor) {
    failures.push(
      `${name} : installé en ${installed}, au-dessus du mineur attendu par le SDK (pin ${pin} → ${sdk.major}.${sdk.minor}.x autorisé au maximum). ` +
        `Vérifier .github/dependabot.yml — cette version n'aurait pas dû être proposée.`,
    );
  } else {
    console.log(`  OK  ${name} : ${installed} (SDK attend ${pin})`);
  }
}

if (failures.length > 0) {
  console.error("\nDésalignement SDK détecté :\n");
  for (const failure of failures) {
    console.error(`  [ÉCHEC] ${failure}`);
  }
  console.error(
    "\nCes paquets sont au-dessus de ce que le SDK Expo installé attend d'eux. " +
      "C'est le mode de défaillance des PR #235 et #252 : build cassé ou crash au " +
      "runtime malgré une CI verte de bout en bout.",
  );
  process.exit(1);
}

console.log("\nOK : aucun paquet géré par le SDK Expo n'est au-dessus de son mineur attendu.");
