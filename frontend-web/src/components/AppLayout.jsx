import { useEffect } from 'react'
import { NavLink, useNavigate } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import { useInvitationsBadge } from '../contexts/InvitationsBadgeContext'
import { useMessagesBadge } from '../contexts/MessagesBadgeContext'
import { ROLE_AIDANT, ROLE_LABELS, ROLE_SOIGNANT, getPrimaryRole, getSidebarItems } from '../services/roles'
import './AppLayout.css'

function notifyComingSoon() {
  window.alert('Cette fonctionnalité arrive dans une prochaine version de MedLink.')
}

export default function AppLayout({ children, securityBanner }) {
  const { roles, firstName, logout } = useAuth()
  const navigate = useNavigate()
  const primaryRole = getPrimaryRole(roles)
  const displayName = firstName ?? (primaryRole ? ROLE_LABELS[primaryRole] : 'Utilisateur')
  const sidebarItems = getSidebarItems(roles)
  const canReceiveInvitations = roles.includes(ROLE_AIDANT) || roles.includes(ROLE_SOIGNANT)
  const { pendingInvitationsCount, refresh: refreshPendingInvitationsCount } = useInvitationsBadge()
  const { unreadMessagesCount, refresh: refreshUnreadMessagesCount } = useMessagesBadge()

  useEffect(() => {
    if (canReceiveInvitations) refreshPendingInvitationsCount()
    refreshUnreadMessagesCount()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [canReceiveInvitations])

  return (
    <div className="app-layout">
      <a href="#app-main-content" className="app-skip-link">
        Aller au contenu principal
      </a>

      <header className="app-header">
        <div className="app-header-brand">
          <span className="app-header-logo" aria-hidden="true">
            🛡️
          </span>
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
          <span className="app-header-lock" role="img" aria-label="Connexion sécurisée" title="Connexion sécurisée">
            🔒
          </span>
          <button type="button" className="app-header-logout" onClick={logout}>
            Se déconnecter
          </button>
        </div>
      </header>

      {securityBanner && <p className="app-security-banner">{securityBanner}</p>}

      <div className="app-body">
        <nav className="app-sidebar" aria-label="Navigation principale">
          <ul>
            {sidebarItems.map((item) => {
              const isInvitations = item.key === 'invitations'
              const isMessages = item.to === '/messages'
              const badgeCount = isInvitations ? pendingInvitationsCount : isMessages ? unreadMessagesCount : 0
              const showBadge = badgeCount > 0
              const badgeLabel = !showBadge
                ? ''
                : isInvitations
                  ? ` (${badgeCount} invitation${badgeCount > 1 ? 's' : ''} en attente)`
                  : ` (${badgeCount} message${badgeCount > 1 ? 's' : ''} non lu${badgeCount > 1 ? 's' : ''})`

              return (
                <li key={item.key}>
                  {item.to ? (
                    <NavLink
                      to={item.to}
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
                    <button type="button" onClick={notifyComingSoon}>
                      {item.label}
                    </button>
                  )}
                </li>
              )
            })}
          </ul>
        </nav>

        <main id="app-main-content" className="app-content">
          {children}
        </main>
      </div>
    </div>
  )
}
