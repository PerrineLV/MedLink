import { useEffect, useRef, useState } from 'react';
import { LogOut } from 'lucide-react';
import { NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/useAuth';
import { useInvitationsBadge } from '../contexts/useInvitationsBadge';
import { useMessagesBadge } from '../contexts/useMessagesBadge';
import logo from '../assets/medlink-logo.png';
import {
  ROLE_AIDANT,
  ROLE_LABELS,
  ROLE_SOIGNANT,
  getPrimaryRole,
  getSidebarItems,
} from '../services/roles';
import './AppLayout.css';

function notifyComingSoon() {
  window.alert('Cette fonctionnalité arrive dans une prochaine version de MedLink.');
}

// Bandeau de sécurité (ML-92) : rendu inconditionnellement par AppLayout (pas
// une prop optionnelle par page) pour qu'il soit structurellement impossible
// d'avoir un écran authentifié sans lui — un simple "j'ai oublié de le
// passer" causait un décalage de layout (le contenu "remonte") à chaque
// navigation vers/depuis un écran qui l'omettait.
export const SECURITY_BANNER_TEXT = 'Données chiffrées - accès soignants uniquement';

export default function AppLayout({ children }) {
  const { roles, firstName, logout } = useAuth();
  const navigate = useNavigate();
  const primaryRole = getPrimaryRole(roles);
  const displayName = firstName ?? (primaryRole ? ROLE_LABELS[primaryRole] : 'Utilisateur');
  const sidebarItems = getSidebarItems(roles);
  const canReceiveInvitations = roles.includes(ROLE_AIDANT) || roles.includes(ROLE_SOIGNANT);
  const { pendingInvitationsCount, refresh: refreshPendingInvitationsCount } =
    useInvitationsBadge();
  const { unreadMessagesCount, refresh: refreshUnreadMessagesCount } = useMessagesBadge();

  // Menu burger (ML-63) : la navigation n'est repliée derrière ce panneau
  // que sous le breakpoint mobile (CSS, cf. AppLayout.css) — au-delà, la
  // sidebar reste toujours visible et cet état n'a aucun effet.
  const [isNavOpen, setIsNavOpen] = useState(false);
  const navPanelRef = useRef(null);
  const navToggleRef = useRef(null);
  const closeNav = () => setIsNavOpen(false);

  useEffect(() => {
    if (canReceiveInvitations) refreshPendingInvitationsCount();
    refreshUnreadMessagesCount();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [canReceiveInvitations]);

  useEffect(() => {
    if (!isNavOpen) return undefined;

    const handlePointerDown = (event) => {
      if (
        navPanelRef.current?.contains(event.target) ||
        navToggleRef.current?.contains(event.target)
      ) {
        return;
      }
      closeNav();
    };
    const handleKeyDown = (event) => {
      if (event.key === 'Escape') closeNav();
    };

    document.addEventListener('mousedown', handlePointerDown);
    document.addEventListener('keydown', handleKeyDown);
    return () => {
      document.removeEventListener('mousedown', handlePointerDown);
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [isNavOpen]);

  return (
    <div className="app-layout">
      <a href="#app-main-content" className="app-skip-link">
        Aller au contenu principal
      </a>

      <header className="app-header">
        <div className="app-header-brand">
          <button
            type="button"
            ref={navToggleRef}
            className="app-nav-toggle"
            onClick={() => setIsNavOpen((open) => !open)}
            aria-expanded={isNavOpen}
            aria-controls="app-sidebar-nav"
            aria-label={isNavOpen ? 'Fermer le menu de navigation' : 'Ouvrir le menu de navigation'}
          >
            <span aria-hidden="true">{isNavOpen ? '✕' : '☰'}</span>
          </button>
          <img src={logo} alt="" className="app-header-logo" />
          <div>
            <p className="app-header-title">MedLink</p>
            <p className="app-header-name">{displayName}</p>
          </div>
        </div>
        <div className="app-header-actions">
          {canReceiveInvitations && (
            <button
              type="button"
              className="app-header-bell"
              onClick={() => navigate('/invitations')}
              aria-label={
                pendingInvitationsCount > 0
                  ? `${pendingInvitationsCount} invitation${pendingInvitationsCount > 1 ? 's' : ''} en attente`
                  : 'Invitations en attente'
              }
            >
              <span aria-hidden="true">🔔</span>
              {pendingInvitationsCount > 0 && (
                <span className="app-header-bell-badge" aria-hidden="true">
                  {pendingInvitationsCount}
                </span>
              )}
            </button>
          )}
          <span
            className="app-header-lock"
            role="img"
            aria-label="Connexion sécurisée"
            title="Connexion sécurisée"
          >
            🔒
          </span>
          <button
            type="button"
            className="app-header-logout"
            onClick={logout}
            aria-label="Se déconnecter"
          >
            <LogOut aria-hidden="true" size={18} />
            <span className="app-header-logout-label">Se déconnecter</span>
          </button>
        </div>
      </header>

      <p className="app-security-banner">{SECURITY_BANNER_TEXT}</p>

      <div className="app-body">
        <nav
          id="app-sidebar-nav"
          ref={navPanelRef}
          className={isNavOpen ? 'app-sidebar open' : 'app-sidebar'}
          aria-label="Navigation principale"
        >
          <ul>
            {sidebarItems.map((item) => {
              const isInvitations = item.key === 'invitations';
              const isMessages = item.to === '/messages';
              const badgeCount = isInvitations
                ? pendingInvitationsCount
                : isMessages
                  ? unreadMessagesCount
                  : 0;
              const showBadge = badgeCount > 0;
              const badgeLabel = !showBadge
                ? ''
                : isInvitations
                  ? ` (${badgeCount} invitation${badgeCount > 1 ? 's' : ''} en attente)`
                  : ` (${badgeCount} message${badgeCount > 1 ? 's' : ''} non lu${badgeCount > 1 ? 's' : ''})`;

              return (
                <li key={item.key}>
                  {item.to ? (
                    <NavLink
                      to={item.to}
                      onClick={closeNav}
                      className={({ isActive }) => (isActive ? 'active' : undefined)}
                      aria-label={showBadge ? `${item.label}${badgeLabel}` : undefined}
                    >
                      {item.label}
                      {showBadge && (
                        <span className="app-sidebar-badge" aria-hidden="true">
                          {badgeCount}
                        </span>
                      )}
                    </NavLink>
                  ) : (
                    <button
                      type="button"
                      onClick={() => {
                        closeNav();
                        notifyComingSoon();
                      }}
                    >
                      {item.label}
                    </button>
                  )}
                </li>
              );
            })}
          </ul>

          <p className="app-sidebar-version">v{import.meta.env.VITE_APP_VERSION}</p>
        </nav>

        <main id="app-main-content" className="app-content">
          {children}
        </main>
      </div>
    </div>
  );
}
