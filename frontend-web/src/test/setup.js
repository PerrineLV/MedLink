// ML-163 : amorçage commun à tous les tests, chargé par `setupFiles` dans
// vite.config.js.

// Ajoute les matchers de @testing-library/jest-dom (`toBeInTheDocument`, etc.)
// à l'`expect` de Vitest. Le sous-chemin `/vitest` est celui qui s'enregistre
// auprès de Vitest plutôt que de Jest.
import '@testing-library/jest-dom/vitest';

import { cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';

// Le nettoyage automatique de Testing Library ne s'active que si les globales
// du lanceur sont injectées (`globals: true`), ce que ce projet ne fait pas
// volontairement — cf. le commentaire du bloc `test` dans vite.config.js. Sans
// ce nettoyage explicite, les rendus s'accumuleraient dans le même document
// d'un test à l'autre et une requête `getByText` finirait par trouver deux
// occurrences, ou par en trouver une laissée par le test précédent.
afterEach(() => {
  cleanup();
});
