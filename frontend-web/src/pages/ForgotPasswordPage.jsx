import { useState } from 'react';
import { Link, Navigate } from 'react-router-dom';
import { useAuth } from '../contexts/useAuth';
import { getHomeRoute } from '../services/roles';
import { requestPasswordReset } from '../services/authService';
import './LoginPage.css';

const GENERIC_ERROR = "Impossible d'envoyer la demande, réessayez.";

export default function ForgotPasswordPage() {
  const { isAuthenticated, roles } = useAuth();
  const [email, setEmail] = useState('');
  const [error, setError] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [confirmationMessage, setConfirmationMessage] = useState(null);

  if (isAuthenticated) {
    return <Navigate to={getHomeRoute(roles)} replace />;
  }

  const handleSubmit = async (event) => {
    event.preventDefault();
    setError(null);
    setIsSubmitting(true);

    try {
      const { message } = await requestPasswordReset(email);
      setConfirmationMessage(message);
    } catch (requestError) {
      if (requestError.response?.status === 429) {
        setError(requestError.response.data?.message ?? 'Trop de demandes, réessayez plus tard.');
      } else {
        setError(GENERIC_ERROR);
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <main className="login-page">
      <div className="login-card">
        <h1>MedLink</h1>
        <p className="login-subtitle">Mot de passe oublié</p>

        {confirmationMessage ? (
          <p className="login-success" role="status">
            {confirmationMessage}
          </p>
        ) : (
          <form onSubmit={handleSubmit} noValidate>
            <div className="form-field">
              <label htmlFor="email">Adresse e-mail</label>
              <input
                id="email"
                name="email"
                type="email"
                autoComplete="username"
                required
                value={email}
                onChange={(event) => setEmail(event.target.value)}
              />
            </div>

            {error && (
              <p className="login-error" role="alert">
                {error}
              </p>
            )}

            <button type="submit" className="login-submit" disabled={isSubmitting}>
              {isSubmitting ? 'Envoi…' : 'Envoyer le lien de réinitialisation'}
            </button>
          </form>
        )}

        <p className="login-register-link">
          <Link to="/login">Retour à la connexion</Link>
        </p>
      </div>
    </main>
  );
}
