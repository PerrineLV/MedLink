import { describe, expect, it } from 'vitest';
import {
  ROLE_ADMIN,
  ROLE_AIDANT,
  ROLE_LABELS,
  ROLE_PATIENT,
  ROLE_SOIGNANT,
  formatSoignantName,
  getHomeRoute,
  getPrimaryRole,
  getSidebarItems,
} from './roles';

// ML-163 — Couverture du module de rôles du front web.
//
// Ces tests décrivent le comportement ACTUEL, y compris là où il paraît
// discutable : le ticket interdit de modifier `roles.js` pour le rendre plus
// satisfaisant. Le seul point litigieux restant — le défaut ouvrant de
// `getSidebarItems` — est signalé en commentaire à l'endroit où il est
// constaté, et suivi dans ML-170. Le jour où il sera corrigé, c'est ce
// test-là qui devra changer, et c'est précisément ce qu'on veut : que la
// décision soit visible plutôt que silencieuse.

const keysOf = (items) => items.map((item) => item.key);

describe('ROLE_LABELS', () => {
  it('donne un libellé lisible pour chacun des quatre rôles', () => {
    expect(ROLE_LABELS[ROLE_PATIENT]).toBe('Patient');
    expect(ROLE_LABELS[ROLE_AIDANT]).toBe('Aidant');
    expect(ROLE_LABELS[ROLE_SOIGNANT]).toBe('Soignant');
    expect(ROLE_LABELS[ROLE_ADMIN]).toBe('Administrateur');
  });

  it('ne renvoie rien pour un rôle inconnu', () => {
    expect(ROLE_LABELS.ROLE_INCONNU).toBeUndefined();
  });
});

describe('formatSoignantName', () => {
  it('préfixe le titre professionnel quand il est renseigné', () => {
    expect(formatSoignantName('Claire', 'Bernard', 'Dr')).toBe('Dr Claire Bernard');
  });

  it('omet le préfixe sans jamais perdre le nom (titre absent, nul ou vide)', () => {
    // Les trois formes que peut prendre un titre non renseigné selon qu'il
    // vient du formulaire d'inscription, de l'API ou d'un JWT ancien.
    expect(formatSoignantName('Claire', 'Bernard', undefined)).toBe('Claire Bernard');
    expect(formatSoignantName('Claire', 'Bernard', null)).toBe('Claire Bernard');
    expect(formatSoignantName('Claire', 'Bernard', '')).toBe('Claire Bernard');
  });
});

// `getPrimaryRole` applique l'ordre Admin > Soignant > Aidant > Patient, qui
// n'est pas celui de `getHomeRoute` plus bas. Ce n'est pas une incohérence :
// ses deux seuls usages sont de l'affichage — libellé de repli dans
// `AppLayout` quand le prénom manque, et libellé du rôle sur « Mon compte ».
// Afficher le rôle le plus élevé est légitime pour une étiquette, et sans
// rapport avec la question « vers quel écran envoyer l'utilisateur ».
describe('getPrimaryRole', () => {
  it('renvoie le rôle lui-même quand l’utilisateur n’en porte qu’un', () => {
    expect(getPrimaryRole([ROLE_PATIENT])).toBe(ROLE_PATIENT);
    expect(getPrimaryRole([ROLE_AIDANT])).toBe(ROLE_AIDANT);
    expect(getPrimaryRole([ROLE_SOIGNANT])).toBe(ROLE_SOIGNANT);
    expect(getPrimaryRole([ROLE_ADMIN])).toBe(ROLE_ADMIN);
  });

  it('renvoie null plutôt qu’un rôle par défaut quand rien ne correspond', () => {
    expect(getPrimaryRole([])).toBeNull();
    expect(getPrimaryRole(['ROLE_INCONNU'])).toBeNull();
    expect(getPrimaryRole()).toBeNull();
  });
});

describe('getHomeRoute', () => {
  it('envoie chaque rôle vers son écran d’accueil', () => {
    expect(getHomeRoute([ROLE_SOIGNANT])).toBe('/patients');
    expect(getHomeRoute([ROLE_PATIENT])).toBe('/journal');
    expect(getHomeRoute([ROLE_AIDANT])).toBe('/journal');
    expect(getHomeRoute([ROLE_ADMIN])).toBe('/admin/users');
  });

  it('renvoie vers /login plutôt que vers une page en impasse', () => {
    // ML-62 : il n'existe plus de tableau de bord générique. Une session sans
    // rôle exploitable ne doit donc pas atterrir sur un écran vide.
    expect(getHomeRoute([])).toBe('/login');
    expect(getHomeRoute(['ROLE_INCONNU'])).toBe('/login');
    expect(getHomeRoute()).toBe('/login');
  });
});

describe('getSidebarItems', () => {
  it('donne au patient ses raccourcis, dont « Mes liaisons »', () => {
    expect(keysOf(getSidebarItems([ROLE_PATIENT]))).toEqual([
      'journal',
      'liaisons',
      'messagerie',
      'rdv',
      'export',
      'compte',
    ]);
  });

  it('retire « Mes liaisons » à l’aidant et lui donne « Invitations »', () => {
    // RGPD, consentement d'abord : un aidant ne gère jamais les liaisons de
    // consentement du patient, il ne voit que les invitations qu'il a reçues.
    const keys = keysOf(getSidebarItems([ROLE_AIDANT]));

    expect(keys).not.toContain('liaisons');
    expect(keys).toContain('invitations');
    expect(keys).toEqual(['journal', 'messagerie', 'rdv', 'export', 'compte', 'invitations']);
  });

  it('donne à l’admin le menu d’administration, sans écran de soin', () => {
    const keys = keysOf(getSidebarItems([ROLE_ADMIN]));

    expect(keys).toEqual(['utilisateurs', 'supervision', 'compte']);
    expect(keys).not.toContain('patients');
    expect(keys).not.toContain('journal');
  });

  it('donne au soignant le menu de suivi des patients', () => {
    expect(keysOf(getSidebarItems([ROLE_SOIGNANT]))).toEqual([
      'patients',
      'invitations',
      'messages',
      'agenda',
      'export',
      'compte',
    ]);
  });

  // ATTENTION — comportement constaté, pas comportement souhaitable.
  //
  // La branche par défaut de `getSidebarItems` est le menu SOIGNANT. Une
  // session sans rôle, ou portant un rôle inconnu, se voit donc proposer
  // « Patients » et « Invitations » : le défaut est ouvrant plutôt que
  // fermant.
  //
  // Aucune donnée n'est exposée — les routes restent gardées par
  // ProtectedRoute, et `getHomeRoute` renvoie ces mêmes sessions vers /login.
  // Mais la défense ne tient qu'à la couche d'à côté. Corrigé par ML-170 ; ce
  // test échouera alors, et c'est le signal attendu.
  it('retombe sur le menu soignant quand aucun rôle ne correspond (défaut ouvrant, ML-170)', () => {
    expect(keysOf(getSidebarItems([]))).toContain('patients');
    expect(keysOf(getSidebarItems(['ROLE_INCONNU']))).toContain('patients');
    expect(keysOf(getSidebarItems())).toContain('patients');
  });
});

describe('cohérence entre getHomeRoute et getSidebarItems', () => {
  it('pose chaque rôle sur un écran présent dans son propre menu', () => {
    // C'est la propriété qui compte : arriver sur un écran absent de sa barre
    // latérale laisse l'utilisateur sans rien pour y revenir, et sans aucun
    // élément de menu marqué actif.
    expect(getHomeRoute([ROLE_SOIGNANT])).toBe('/patients');
    expect(keysOf(getSidebarItems([ROLE_SOIGNANT]))).toContain('patients');

    expect(getHomeRoute([ROLE_ADMIN])).toBe('/admin/users');
    expect(keysOf(getSidebarItems([ROLE_ADMIN]))).toContain('utilisateurs');

    expect(getHomeRoute([ROLE_PATIENT])).toBe('/journal');
    expect(keysOf(getSidebarItems([ROLE_PATIENT]))).toContain('journal');

    expect(getHomeRoute([ROLE_AIDANT])).toBe('/journal');
    expect(keysOf(getSidebarItems([ROLE_AIDANT]))).toContain('journal');
  });
});

// Un compte MedLink porte exactement un rôle : c'est admin OU soignant OU
// patient OU aidant, jamais une combinaison. Vérifié côté backend — les trois
// seuls appels à `setRoles` passent un tableau à un élément
// (`RegistrationService:44`, `AppFixtures:145`, `SeedDemoDataCommand:179`), et
// aucun chemin de code n'en ajoute un second. La colonne JSON le permettrait
// techniquement, rien ne l'écrit.
//
// Les fonctions de ce module acceptent pourtant un tableau et savent arbitrer
// entre plusieurs rôles. Cette couverture-là est donc défensive : elle fige le
// comportement de ce code d'arbitrage pour qu'un changement de règle ne passe
// pas inaperçu, sans prétendre décrire un compte qui existe aujourd'hui.
describe('comptes multi-rôles — couverture défensive', () => {
  it('getPrimaryRole applique la priorité Admin > Soignant > Aidant > Patient', () => {
    expect(getPrimaryRole([ROLE_PATIENT, ROLE_ADMIN])).toBe(ROLE_ADMIN);
    expect(getPrimaryRole([ROLE_PATIENT, ROLE_SOIGNANT])).toBe(ROLE_SOIGNANT);
    expect(getPrimaryRole([ROLE_PATIENT, ROLE_AIDANT])).toBe(ROLE_AIDANT);
    expect(getPrimaryRole([ROLE_SOIGNANT, ROLE_ADMIN])).toBe(ROLE_ADMIN);
  });

  it('getPrimaryRole ignore l’ordre du tableau reçu', () => {
    // L'API n'offre aucune garantie sur l'ordre des rôles dans le JWT : si la
    // priorité en dépendait, le rôle retenu varierait d'une connexion à
    // l'autre sans que rien ne le signale.
    expect(getPrimaryRole([ROLE_ADMIN, ROLE_PATIENT])).toBe(ROLE_ADMIN);
    expect(getPrimaryRole([ROLE_PATIENT, ROLE_ADMIN])).toBe(ROLE_ADMIN);
  });
});
