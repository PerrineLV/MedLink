import { getStateFromPath } from '@react-navigation/native';
import { safeGetStateFromPath, MAX_DEEP_LINK_PATH_LENGTH } from '../deepLinking';

// Le parseur par défaut de React Navigation est mocké : ce qu'on veut prouver,
// c'est qu'il n'est PAS appelé sur une entrée hors plafond. C'est lui qui mène à
// query-string, donc au décodeur vulnérable (GHSA-vcc3-ghjq-m6fr) — l'espion sur
// cet appel est donc l'assertion qui porte la valeur de sécurité du test.
jest.mock('@react-navigation/native', () => ({
  getStateFromPath: jest.fn(() => ({ routes: [{ name: 'ResetPassword' }] })),
}));

// Jeton de réinitialisation tel que produit par le backend :
// bin2hex(random_bytes(32)) dans PasswordResetService, soit 64 caractères hex.
const RESET_TOKEN = 'a1b2c3d4'.repeat(8);
const LEGITIMATE_PATH = `reset-password?token=${RESET_TOKEN}`;

const config = { screens: { ResetPassword: 'reset-password' } };

beforeEach(() => {
  jest.clearAllMocks();
});

describe('MAX_DEEP_LINK_PATH_LENGTH', () => {
  // Garde-fou sur le garde-fou : si quelqu'un resserre le plafond sous la
  // longueur d'un lien légitime, la réinitialisation de mot de passe (ML-78)
  // casserait en production. Ce test doit le dire avant.
  it('laisse passer le deep link légitime le plus long', () => {
    expect(LEGITIMATE_PATH.length).toBe(85);
    expect(MAX_DEEP_LINK_PATH_LENGTH).toBeGreaterThan(LEGITIMATE_PATH.length);
  });
});

describe('safeGetStateFromPath', () => {
  it('transmet un deep link légitime au parseur et retourne son résultat', () => {
    const state = safeGetStateFromPath(LEGITIMATE_PATH, config);

    expect(getStateFromPath).toHaveBeenCalledWith(LEGITIMATE_PATH, config);
    expect(state).toEqual({ routes: [{ name: 'ResetPassword' }] });
  });

  it('accepte un path exactement à la longueur du plafond', () => {
    // Vérifie que la comparaison est bien `>` et non `>=`.
    const path = 'x'.repeat(MAX_DEEP_LINK_PATH_LENGTH);

    expect(safeGetStateFromPath(path, config)).toBeDefined();
    expect(getStateFromPath).toHaveBeenCalled();
  });

  it('rejette un path dépassant le plafond d’un seul caractère', () => {
    const path = 'x'.repeat(MAX_DEEP_LINK_PATH_LENGTH + 1);

    expect(safeGetStateFromPath(path, config)).toBeUndefined();
    expect(getStateFromPath).not.toHaveBeenCalled();
  });

  it("rejette le payload de l'advisory avant qu'il n'atteigne le décodeur", () => {
    // Forme exploitée par GHSA-vcc3-ghjq-m6fr : une longue séquence de
    // pourcent-encodage malformé, dont le décodage a un coût exponentiel.
    // C'est LE cas qui prouve que le garde-fou mord sur le vecteur réel.
    const payload = `reset-password?token=${'%'.repeat(5000)}`;

    expect(safeGetStateFromPath(payload, config)).toBeUndefined();
    expect(getStateFromPath).not.toHaveBeenCalled();
  });

  it.each([
    ['null', null],
    ['undefined', undefined],
    ['un nombre', 42],
    ['un objet', {}],
  ])('rejette une entrée non-chaîne (%s) sans lever d’exception', (_label, path) => {
    expect(() => safeGetStateFromPath(path, config)).not.toThrow();
    expect(safeGetStateFromPath(path, config)).toBeUndefined();
    expect(getStateFromPath).not.toHaveBeenCalled();
  });
});
