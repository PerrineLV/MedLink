import { readFileSync } from 'node:fs';
import { defineConfig } from 'vite';
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
