import { getStateFromPath as getStateFromPathDefault } from '@react-navigation/native';

/**
 * Garde-fou sur les deep links entrants (ML-160).
 *
 * NE PAS SUPPRIMER : ce n'est pas une optimisation, c'est un contrôle de sécurité.
 *
 * React Navigation parse la query string des liens entrants via query-string@7,
 * qui délègue le décodage à decode-uri-component@0.2.2. Cette version est
 * vulnérable à GHSA-vcc3-ghjq-m6fr : une entrée en pourcent-encodage malformé
 * provoque un décodage au coût exponentiel, donc un freeze de l'application.
 *
 * Aucune montée de version n'est possible : decode-uri-component est passé
 * ESM-only en 0.4.0, les seules versions CommonJS (0.2.2 et 0.3.0) sont toutes
 * deux vulnérables, et query-string@7 les consomme en `require()`. Forcer une
 * version corrigée par un override npm casse l'interop et fait échouer tout
 * deep link (vérifié — voir ML-160).
 *
 * Le coût du décodage étant gouverné par la taille de l'entrée, on la borne en
 * amont : un path trop long est ignoré avant d'atteindre le parseur.
 *
 * À réexaminer quand React Navigation passera à query-string 9 (ESM) : l'override
 * redeviendra possible et ce garde-fou pourra être retiré.
 */

/**
 * Plafond de longueur d'un path de deep link, en caractères.
 *
 * Calcul : la seule route de deep link du projet est `reset-password?token=...`
 * (ML-78). Le jeton est généré côté backend par `bin2hex(random_bytes(32))`
 * (PasswordResetService), soit 64 caractères hexadécimaux. Le path légitime le
 * plus long mesure donc `reset-password?token=` (21) + 64 = 85 caractères.
 *
 * 256 laisse trois fois la marge nécessaire — de quoi absorber une future route
 * sans retoucher cette valeur — tout en gardant le coût du décodage borné.
 */
export const MAX_DEEP_LINK_PATH_LENGTH = 256;

/**
 * Valide un path de deep link avant de le confier au parseur de React Navigation.
 *
 * Un path hors plafond est ignoré (retour `undefined`) plutôt que rejeté par une
 * exception : React Navigation ouvre alors l'application normalement sur son
 * écran par défaut. Un lien hostile ne doit pas faire crasher l'app.
 */
export function safeGetStateFromPath(path, config) {
  if (typeof path !== 'string' || path.length > MAX_DEEP_LINK_PATH_LENGTH) {
    return undefined;
  }

  return getStateFromPathDefault(path, config);
}
