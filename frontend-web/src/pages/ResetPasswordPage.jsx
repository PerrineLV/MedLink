import { useState } from 'react';
import { Link, Navigate, useNavigate, useSearchParams } from 'react-router-dom';
import { useAuth } from '../contexts/useAuth';
import { getHomeRoute } from '../services/roles';
import { confirmPasswordReset } from '../services/authService';
import './ResetPasswordPage.css';

const GENERIC_ERROR = 'Impossible de réinitialiser le mot de passe, réessayez.';

export default function ResetPasswordPage() {
  const { isAuthenticated, roles } = useAuth();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token');

  const [newPassword, setNewPassword] = useState('');
  const [confirmNewPassword, setConfirmNewPassword] = useState('');
  const [fieldErrors, setFieldErrors] = useState({});
  const [bannerError, setBannerError] = useState(null);
  const [linkExpired, setLinkExpired] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  if (isAuthenticated) {
    return <Navigate to={getHomeRoute(roles)} replace />;
  }

  if (!token) {
    return (
      <main className="reset-password-page">
        <div className="reset-password-card">
          <h1>MedLink</h1>
          <p className="reset-password-error" role="alert">
            Lien de réinitialisation invalide. Merci de refaire une demande.
          </p>
          <p className="reset-password-back-link">
            <Link to="/forgot-password">Refaire une demande</Link>
          </p>
        </div>
      </main>
    );
  }

  const handleSubmit = async (event) => {
    event.preventDefault();
    setFieldErrors({});
    setBannerError(null);
    setLinkExpired(false);

    if (newPassword !== confirmNewPassword) {
      setFieldErrors({ confirmNewPassword: 'Les mots de passe ne correspondent pas.' });
      return;
    }

    setIsSubmitting(true);

    try {
      await confirmPasswordReset(token, newPassword);
      navigate('/login', { replace: true, state: { passwordReset: true } });
    } catch (requestError) {
      const status = requestError.response?.status;
      const message = requestError.response?.data?.message;

      if (status === 410) {
        setLinkExpired(true);
        setBannerError(message ?? GENERIC_ERROR);
      } else if (message && /mot de passe doit contenir/i.test(message)) {
        setFieldErrors({ newPassword: message });
      } else {
        setBannerError(message ?? GENERIC_ERROR);
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <main className="reset-password-page">
      <div className="reset-password-card">
        <h1>MedLink</h1>
        <p className="reset-password-subtitle">Choisissez un nouveau mot de passe</p>

        <form onSubmit={handleSubmit} noValidate>
          <div className="form-field">
            <label htmlFor="newPassword">Nouveau mot de passe</label>
            <input
              id="newPassword"
              name="newPassword"
              type="password"
              autoComplete="new-password"
              required
              value={newPassword}
              onChange={(event) => setNewPassword(event.target.value)}
              aria-describedby={fieldErrors.newPassword ? 'newPassword-error' : undefined}
            />
            {fieldErrors.newPassword && (
              <p id="newPassword-error" className="field-error" role="alert">
                {fieldErrors.newPassword}
              </p>
            )}
          </div>

          <div className="form-field">
            <label htmlFor="confirmNewPassword">Confirmer le nouveau mot de passe</label>
            <input
              id="confirmNewPassword"
              name="confirmNewPassword"
              type="password"
              autoComplete="new-password"
              required
              value={confirmNewPassword}
              onChange={(event) => setConfirmNewPassword(event.target.value)}
              aria-describedby={
                fieldErrors.confirmNewPassword ? 'confirmNewPassword-error' : undefined
              }
            />
            {fieldErrors.confirmNewPassword && (
              <p id="confirmNewPassword-error" className="field-error" role="alert">
                {fieldErrors.confirmNewPassword}
              </p>
            )}
          </div>

          {bannerError && (
            <p className="reset-password-error" role="alert">
              {bannerError}
            </p>
          )}

          {linkExpired && (
            <p className="reset-password-back-link">
              <Link to="/forgot-password">Refaire une demande</Link>
            </p>
          )}

          <button type="submit" className="reset-password-submit" disabled={isSubmitting}>
            {isSubmitting ? 'Réinitialisation…' : 'Réinitialiser le mot de passe'}
          </button>
        </form>

        <p className="reset-password-back-link">
          <Link to="/login">Retour à la connexion</Link>
        </p>
      </div>
    </main>
  );
}
