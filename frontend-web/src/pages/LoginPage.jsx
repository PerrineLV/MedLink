import { useState } from 'react';
import { Link, Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/useAuth';
import { getHomeRoute } from '../services/roles';
import './LoginPage.css';

const GENERIC_ERROR = 'Identifiants incorrects';

export default function LoginPage() {
  const { login, isAuthenticated, roles } = useAuth();
  const location = useLocation();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const registered = location.state?.registered === true;
  const passwordReset = location.state?.passwordReset === true;

  if (isAuthenticated) {
    return <Navigate to={getHomeRoute(roles)} replace />;
  }

  const handleSubmit = async (event) => {
    event.preventDefault();
    setError(null);
    setIsSubmitting(true);

    try {
      await login(email, password);
    } catch (requestError) {
      // 429 = rate-limited (ML-20): surface that explicit reason. Anything
      // else (bad password, unknown email...) must stay generic so we don't
      // leak whether the account exists.
      if (requestError.response?.status === 429) {
        setError(requestError.response.data?.message ?? 'Trop de tentatives, réessayez plus tard.');
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
        <p className="login-subtitle">Connectez-vous à votre espace</p>

        {registered && (
          <p className="login-success" role="status">
            Compte créé, vous pouvez vous connecter.
          </p>
        )}

        {passwordReset && (
          <p className="login-success" role="status">
            Mot de passe réinitialisé, vous pouvez vous connecter.
          </p>
        )}

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

          <div className="form-field">
            <label htmlFor="password">Mot de passe</label>
            <input
              id="password"
              name="password"
              type="password"
              autoComplete="current-password"
              required
              value={password}
              onChange={(event) => setPassword(event.target.value)}
            />
          </div>

          {error && (
            <p className="login-error" role="alert">
              {error}
            </p>
          )}

          <button type="submit" className="login-submit" disabled={isSubmitting}>
            {isSubmitting ? 'Connexion…' : 'Se connecter'}
          </button>
        </form>

        <p className="login-forgot-password-link">
          <Link to="/forgot-password">Mot de passe oublié ?</Link>
        </p>

        <p className="login-register-link">
          Pas encore de compte ? <Link to="/register">Créer un compte</Link>
        </p>
      </div>
    </main>
  );
}
