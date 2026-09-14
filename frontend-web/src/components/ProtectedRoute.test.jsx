import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import ProtectedRoute from './ProtectedRoute';
import { AuthContext } from '../contexts/useAuth';
import { ROLE_ADMIN, ROLE_AIDANT, ROLE_PATIENT, ROLE_SOIGNANT } from '../services/roles';

// ML-163 — Couverture du garde de routage du front web.
//
// Le contexte d'authentification est fourni pour de vrai via
// `AuthContext.Provider`, plutôt que `useAuth` remplacé par un mock : le hook
// réel est ainsi exercé, et un test ne peut pas continuer à passer si la forme
// de la valeur du contexte change. Un mock, lui, resterait figé sur l'ancienne
// forme et donnerait une assurance fausse.

const CONTENU_PROTEGE = 'contenu réservé';
const ECRAN_CONNEXION = 'écran de connexion';
const ECRAN_JOURNAL = 'journal du patient';
const ECRAN_PATIENTS = 'liste des patients';
const ECRAN_ADMIN = 'gestion des comptes';

function renderProtectedRoute({ isAuthenticated = true, userRoles = [], roles } = {}) {
  return render(
    <AuthContext.Provider value={{ isAuthenticated, roles: userRoles }}>
      <MemoryRouter initialEntries={['/protege']}>
        <Routes>
          <Route element={<ProtectedRoute roles={roles} />}>
            <Route path="/protege" element={<p>{CONTENU_PROTEGE}</p>} />
          </Route>
          {/* Cibles de redirection, pour distinguer *où* l'utilisateur est
              renvoyé et pas seulement qu'il l'a été. */}
          <Route path="/login" element={<p>{ECRAN_CONNEXION}</p>} />
          <Route path="/journal" element={<p>{ECRAN_JOURNAL}</p>} />
          <Route path="/patients" element={<p>{ECRAN_PATIENTS}</p>} />
          <Route path="/admin/users" element={<p>{ECRAN_ADMIN}</p>} />
        </Routes>
      </MemoryRouter>
    </AuthContext.Provider>,
  );
}

// Toute la valeur de sécurité de ces tests tient ici : vérifier qu'une
// redirection a eu lieu ne dit rien du contenu protégé, qui pourrait être
// rendu avant elle. C'est son ABSENCE qu'il faut affirmer.
function attendRefus(ecranAttendu) {
  expect(screen.getByText(ecranAttendu)).toBeInTheDocument();
  expect(screen.queryByText(CONTENU_PROTEGE)).not.toBeInTheDocument();
}

describe('ProtectedRoute — session non authentifiée', () => {
  it('renvoie vers /login sur une route ouverte à tous les authentifiés', () => {
    renderProtectedRoute({ isAuthenticated: false });

    attendRefus(ECRAN_CONNEXION);
  });

  it('renvoie vers /login sur une route restreinte, quel que soit le rôle exigé', () => {
    renderProtectedRoute({ isAuthenticated: false, roles: [ROLE_SOIGNANT] });

    attendRefus(ECRAN_CONNEXION);
  });

  it('renvoie vers /login même si la session porte des rôles sans jeton valide', () => {
    // Cas réel : le jeton a expiré mais les rôles lus au démarrage sont encore
    // en mémoire. L'authentification doit primer sur le rôle.
    renderProtectedRoute({
      isAuthenticated: false,
      userRoles: [ROLE_ADMIN],
      roles: [ROLE_ADMIN],
    });

    attendRefus(ECRAN_CONNEXION);
  });
});

describe('ProtectedRoute — session authentifiée et habilitée', () => {
  it('rend le contenu protégé quand le rôle correspond', () => {
    renderProtectedRoute({ userRoles: [ROLE_SOIGNANT], roles: [ROLE_SOIGNANT] });

    expect(screen.getByText(CONTENU_PROTEGE)).toBeInTheDocument();
  });

  // Couverture défensive du `.some()` de la garde : un compte MedLink porte en
  // pratique exactement un rôle (cf. le commentaire correspondant dans
  // roles.test.js), mais la garde sait arbitrer et ce comportement doit rester
  // figé.
  it('rend le contenu quand un seul des rôles de l’utilisateur correspond', () => {
    renderProtectedRoute({
      userRoles: [ROLE_PATIENT, ROLE_SOIGNANT],
      roles: [ROLE_SOIGNANT],
    });

    expect(screen.getByText(CONTENU_PROTEGE)).toBeInTheDocument();
  });

  it('rend le contenu quand la route accepte plusieurs rôles', () => {
    renderProtectedRoute({
      userRoles: [ROLE_AIDANT],
      roles: [ROLE_PATIENT, ROLE_AIDANT],
    });

    expect(screen.getByText(CONTENU_PROTEGE)).toBeInTheDocument();
  });
});

describe('ProtectedRoute — route protégée par la seule authentification', () => {
  // Sans prop `roles`, la garde ne vérifie que le jeton : tout rôle passe.
  it.each([
    ['patient', ROLE_PATIENT],
    ['aidant', ROLE_AIDANT],
    ['soignant', ROLE_SOIGNANT],
    ['admin', ROLE_ADMIN],
  ])('rend le contenu pour un %s authentifié', (_libelle, role) => {
    renderProtectedRoute({ userRoles: [role] });

    expect(screen.getByText(CONTENU_PROTEGE)).toBeInTheDocument();
  });
});

describe('ProtectedRoute — session authentifiée mais non habilitée', () => {
  // Les deux combinaisons qui comptent pour ce projet : un patient qui
  // atteindrait un écran soignant verrait le suivi d'autrui, un soignant qui
  // atteindrait un écran admin accéderait à la gestion des comptes.
  it('refuse un patient sur une route soignant et le renvoie vers son journal', () => {
    renderProtectedRoute({ userRoles: [ROLE_PATIENT], roles: [ROLE_SOIGNANT] });

    attendRefus(ECRAN_JOURNAL);
  });

  it('refuse un soignant sur une route admin et le renvoie vers ses patients', () => {
    renderProtectedRoute({ userRoles: [ROLE_SOIGNANT], roles: [ROLE_ADMIN] });

    attendRefus(ECRAN_PATIENTS);
  });

  it('refuse un aidant sur une route admin', () => {
    renderProtectedRoute({ userRoles: [ROLE_AIDANT], roles: [ROLE_ADMIN] });

    attendRefus(ECRAN_JOURNAL);
  });

  it('refuse un admin sur une route soignant', () => {
    // L'admin n'est pas un super-utilisateur des écrans de soin : il n'a aucun
    // patient rattaché, et le backend le lui refuserait de toute façon.
    renderProtectedRoute({ userRoles: [ROLE_ADMIN], roles: [ROLE_SOIGNANT] });

    attendRefus(ECRAN_ADMIN);
  });

  it('renvoie vers /login une session authentifiée sans aucun rôle', () => {
    // Ne devrait pas arriver, mais c'est le cas où un défaut ouvrant ferait le
    // plus de dégâts : pas de rôle ne doit jamais valoir tous les rôles.
    renderProtectedRoute({ userRoles: [], roles: [ROLE_SOIGNANT] });

    attendRefus(ECRAN_CONNEXION);
  });

  it('renvoie vers /login une session portant un rôle inconnu', () => {
    renderProtectedRoute({ userRoles: ['ROLE_INCONNU'], roles: [ROLE_SOIGNANT] });

    attendRefus(ECRAN_CONNEXION);
  });
});
