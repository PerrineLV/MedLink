import { NavLink } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import { ROLE_LABELS, getPrimaryRole } from '../services/roles'
import './AppLayout.css'

const SIDEBAR_ITEMS = [
  { key: 'dashboard', label: 'Tableau de bord', to: '/dashboard' },
  { key: 'patients', label: 'Patients', to: '/patients' },
  { key: 'messages', label: 'Messages', to: null },
  { key: 'agenda', label: 'Agenda', to: null },
  { key: 'parametres', label: 'Paramètres', to: null },
]

function notifyComingSoon() {
  window.alert('Cette fonctionnalité arrive dans une prochaine version de MedLink.')
}

export default function AppLayout({ children }) {
  const { roles, firstName, logout } = useAuth()
  const primaryRole = getPrimaryRole(roles)
  const displayName = firstName ?? (primaryRole ? ROLE_LABELS[primaryRole] : 'Utilisateur')

  return (
    <div className="app-layout">
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

      <div className="app-body">
        <nav className="app-sidebar" aria-label="Navigation principale">
          <ul>
            {SIDEBAR_ITEMS.map((item) => (
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

        <main className="app-content">{children}</main>
      </div>
    </div>
  )
}
