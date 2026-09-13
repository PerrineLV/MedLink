// ML-164 — FICHIER TEMPORAIRE de vérification de la CI. NE JAMAIS MERGER.
//
// Assertion volontairement fausse : l'étape "Tests unitaires (Jest)" du job
// "Frontend mobile checks" doit échouer. Ce job est un check requis par le
// ruleset "epic branches", donc le merge vers la branche d'epic doit être
// refusé.
//
// Une assertion fausse est choisie à dessein plutôt qu'un défaut de format ou
// une erreur de lint : le hook de pre-commit lance `prettier --write` et
// `eslint --fix` sur les fichiers indexés, et réparerait silencieusement une
// sonde de ce type avant qu'elle n'atteigne GitHub — c'est exactement ce qui
// s'est produit avec la première tentative (PR #221, verte à tort).
//
// Supprimer ce fichier et fermer la PR sans merger une fois la vérification
// faite.
describe('ML-164 — sonde de vérification du ruleset', () => {
  it('échoue volontairement pour prouver que la CI bloque le merge', () => {
    expect(1).toBe(2);
  });
});
