// https://docs.expo.dev/guides/using-eslint/
const { defineConfig } = require('eslint/config');
const expoConfig = require('eslint-config-expo/flat');

module.exports = defineConfig([
  expoConfig,
  {
    ignores: ['dist/*'],
  },
  {
    // Globales injectées par Jest à l'exécution (ML-162). Sans ce bloc, tout
    // fichier de test échoue sur `no-undef` — constaté via le hook de
    // pre-commit, qui lance ESLint directement là où `expo lint` ne couvrait
    // pas ces fichiers.
    //
    // Les globales sont listées à la main plutôt qu'importées du paquet
    // `globals` : celui-ci n'est présent que comme dépendance transitive
    // d'ESLint, et en dépendre ici casserait le lint le jour où la chaîne de
    // résolution change.
    files: ['**/__tests__/**/*.js', '**/*.test.js'],
    languageOptions: {
      globals: {
        afterAll: 'readonly',
        afterEach: 'readonly',
        beforeAll: 'readonly',
        beforeEach: 'readonly',
        describe: 'readonly',
        expect: 'readonly',
        it: 'readonly',
        jest: 'readonly',
        test: 'readonly',
      },
    },
  },
]);
