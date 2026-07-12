import { useAuth } from '../contexts/useAuth';
import './SessionExpiryWarning.css';

export default function SessionExpiryWarning() {
  const { sessionExpiryWarning, dismissSessionExpiryWarning, logout } = useAuth();

  if (!sessionExpiryWarning) {
    return null;
  }

  return (
    <div
      className="session-warning"
      role="alertdialog"
      aria-live="assertive"
      aria-label="Expiration de session"
    >
      <p>Votre session va expirer dans 2 minutes par inactivité.</p>
      <div className="session-warning-actions">
        <button
          type="button"
          className="session-warning-stay"
          onClick={dismissSessionExpiryWarning}
        >
          Rester connecté·e
        </button>
        <button type="button" className="session-warning-logout" onClick={logout}>
          Se déconnecter
        </button>
      </div>
    </div>
  );
}
