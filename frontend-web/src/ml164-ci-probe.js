// ML-164 — FICHIER TEMPORAIRE de vérification de la CI. NE JAMAIS MERGER.
//
// Volontairement mal formaté : l'étape "Prettier check" du job
// "Frontend web checks" doit échouer. Ce job étant un check requis par le
// ruleset "epic branches - required status checks", le merge vers la branche
// d'epic doit alors être refusé.
//
// C'est ce refus qui prouve que le ruleset mord réellement — et non
// seulement qu'il est configuré. Supprimer ce fichier et fermer la PR sans
// merger une fois la vérification faite.
export const probe = { a: 1, b: 2 };
