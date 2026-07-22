import { useRef } from 'react';
import { useAuth } from '../contexts/useAuth';
import { useFocusTrap } from '../hooks/useFocusTrap';
import './SessionExpiryWarning.css';

export default function SessionExpiryWarning() {
  const { sessionExpiryWarning, dismissSessionExpiryWarning, logout } = useAuth();
  const dialogRef = useRef(null);

  useFocusTrap(dialogRef, sessionExpiryWarning);

  if (!sessionExpiryWarning) {
    return null;
  }

  return (
    <div
      ref={dialogRef}
      className="session-warning"
      role="alertdialog"
      aria-modal="true"
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
