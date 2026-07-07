import { NavLink } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import { ROLE_LABELS, getPrimaryRole, getSidebarItems } from '../services/roles'
import './AppLayout.css'

function notifyComingSoon() {
  window.alert('Cette fonctionnalité arrive dans une prochaine version de MedLink.')
}

export default function AppLayout({ children, securityBanner }) {
  const { roles, firstName, logout } = useAuth()
  const primaryRole = getPrimaryRole(roles)
  const displayName = firstName ?? (primaryRole ? ROLE_LABELS[primaryRole] : 'Utilisateur')
  const sidebarItems = getSidebarItems(roles)

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
            {sidebarItems.map((item) => (
              <li key={item.key}>
                {item.to ? (
                  <NavLink to={item.to} className={({ isActive }) => (isActive ? 'active' : undefined)}>
                    {item.label}
                  </NavLink>
                ) : (
                  <button type="button" onClick={notifyComingSoon}>
                    {item.label}
                  </button>
                )}
              </li>
            ))}
          </ul>
        </nav>

        <main id="app-main-content" className="app-content">
          {children}
        </main>
      </div>
    </div>
  )
}
