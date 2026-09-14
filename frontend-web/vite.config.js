import { readFileSync } from 'node:fs';
// ML-163 : `defineConfig` vient de `vitest/config` et non de `vite`. C'est un
// sur-ensemble de celui de Vite qui reconnaît le bloc `test` ci-dessous, plutôt
// que de compter sur la tolérance de Vite envers une clé de configuration
// inconnue. Sans risque pour la production : le front web est construit par
// `npm ci && npm run build` (cd.yml, job deploy-frontend), donc avec les
// devDependencies, et aucun `--omit=dev` n'existe dans le dépôt.
import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

const { version } = JSON.parse(readFileSync(new URL('./package.json', import.meta.url)));

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  // Source de vérité unique pour le numéro de version affiché (ML-89) :
  // lu depuis package.json à chaque build (dev et prod), jamais codé en dur
  // dans un composant. Le champ "version" doit rester synchronisé avec le
  // tag Git de la release (cf. section Versionnage du CLAUDE.md).
  define: {
    'import.meta.env.VITE_APP_VERSION': JSON.stringify(version),
  },
  // ML-163 : configuration des tests unitaires. Vitest réutilise la
  // configuration Vite ci-dessus — plugin React, résolution des imports,
  // transformation JSX — au lieu d'exiger une seconde chaîne de
  // transformation comme le ferait Jest sur ce projet.
  test: {
    // Nécessaire pour les tests de composant (@testing-library/react).
    // Appliqué aussi aux tests de logique pure, qui s'en accommodent, plutôt
    // que de maintenir deux environnements pour un gain nul.
    environment: 'jsdom',
    // Pas de `globals: true` : `describe`, `it` et `expect` sont importés
    // explicitement depuis `vitest` dans chaque fichier de test. C'est ce qui
    // évite ici le piège rencontré en ML-162 côté mobile, où les globales
    // injectées par le lanceur de tests faisaient échouer le lint sur 25
    // erreurs `no-undef` — aucune globale n'est injectée, donc aucune
    // configuration de lint à ajuster.
    globals: false,
    setupFiles: ['./src/test/setup.js'],
    include: ['src/**/*.test.{js,jsx}'],
  },
  server: {
    // Listen on all interfaces so the dev server is reachable from outside
    // its Docker container (or from other devices on the LAN).
    host: true,
    proxy: {
      '/api': {
        // Inside the "web" container, "localhost" would point at the
        // container itself, not the backend — docker-compose.yml sets this
        // to "http://app:80" for that case.
        target: process.env.VITE_API_PROXY_TARGET ?? 'http://localhost:8080',
        changeOrigin: true,
      },
    },
  },
});
