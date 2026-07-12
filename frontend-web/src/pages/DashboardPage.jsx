import { useAuth } from '../contexts/useAuth';
import { ROLE_LABELS, getPrimaryRole } from '../services/roles';
import './DashboardPage.css';

export default function DashboardPage() {
  const { roles, logout } = useAuth();
  const primaryRole = getPrimaryRole(roles);
  const roleLabel = primaryRole ? ROLE_LABELS[primaryRole] : 'Utilisateur';

  return (
    <main className="dashboard-page">
      <header className="dashboard-header">
        <h1>Bienvenue</h1>
        <button type="button" onClick={logout}>
          Se déconnecter
        </button>
      </header>
      <p>
        Vous êtes connecté·e en tant que <strong>{roleLabel}</strong>.
      </p>
    </main>
  );
}
