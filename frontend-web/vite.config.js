import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
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
