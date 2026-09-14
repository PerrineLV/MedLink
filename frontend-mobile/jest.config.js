/**
 * Configuration Jest de frontend-mobile (ML-162).
 *
 * Le preset `jest-expo` est celui prévu pour un projet Expo : il applique la
 * transformation Babel du projet (babel-preset-expo), fournit les mocks des
 * modules natifs et pose le `transformIgnorePatterns` nécessaire aux paquets de
 * `node_modules` livrés en ESM ou en syntaxe Flow — que Jest ne sait pas lire
 * tels quels.
 *
 * Sa version doit rester alignée sur celle du SDK Expo du projet. L'installer
 * via `npx expo install jest-expo` plutôt que `npm install` : un paquet de
 * l'écosystème Expo résolu sur un autre SDK casse de façon déroutante (ML-153,
 * ML-154). À réaligner lors de la montée en SDK 57 (ML-159).
 */
module.exports = {
  preset: 'jest-expo',
  testPathIgnorePatterns: ['/node_modules/', '/dist/', '/.expo/'],
  collectCoverageFrom: ['services/**/*.js', 'components/**/*.js', 'screens/**/*.js'],
};
