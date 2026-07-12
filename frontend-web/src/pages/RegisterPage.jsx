import { useState } from 'react';
import { Link, Navigate, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/useAuth';
import { getHomeRoute } from '../services/roles';
import { register } from '../services/authService';
import './RegisterPage.css';

const ROLE_OPTIONS = [
  { value: 'patient', label: 'Patient' },
  { value: 'aidant', label: 'Aidant' },
  { value: 'soignant', label: 'Soignant' },
];

const GENERIC_ERROR = 'Impossible de créer le compte, réessayez.';

export default function RegisterPage() {
  const { isAuthenticated, roles } = useAuth();
  const navigate = useNavigate();

  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [role, setRole] = useState(null);
  const [title, setTitle] = useState('');
  const [consent, setConsent] = useState(false);
  const [fieldErrors, setFieldErrors] = useState({});
  const [bannerError, setBannerError] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  if (isAuthenticated) {
    return <Navigate to={getHomeRoute(roles)} replace />;
  }

  const handleSubmit = async (event) => {
    event.preventDefault();
    setFieldErrors({});
    setBannerError(null);
    setIsSubmitting(true);

    try {
      await register({
        email,
        password,
        firstName,
        lastName,
        role,
        title: role === 'soignant' ? title : undefined,
        consent,
      });
      navigate('/login', { replace: true, state: { registered: true } });
    } catch (requestError) {
      const responseMessage = requestError.response?.data?.message;
      const { fields, message } = mapRegistrationErrorMessageToField(responseMessage);

      if (fields.length > 0) {
        const nextFieldErrors = {};
        fields.forEach((field) => {
          nextFieldErrors[field] = message;
        });
        setFieldErrors(nextFieldErrors);
      } else {
        setBannerError(message ?? GENERIC_ERROR);
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <main className="register-page">
      <div className="register-card">
        <h1>MedLink</h1>
        <p className="register-subtitle">Créer votre compte</p>

        <form onSubmit={handleSubmit} noValidate>
          <div className="form-field">
            <label htmlFor="firstName">Prénom</label>
            <input
              id="firstName"
              name="firstName"
              type="text"
              autoComplete="given-name"
              required
              maxLength={100}
              value={firstName}
              onChange={(event) => setFirstName(event.target.value)}
              aria-describedby={fieldErrors.firstName ? 'firstName-error' : undefined}
            />
            {fieldErrors.firstName && (
              <p id="firstName-error" className="field-error" role="alert">
                {fieldErrors.firstName}
              </p>
            )}
          </div>

          <div className="form-field">
            <label htmlFor="lastName">Nom</label>
            <input
              id="lastName"
              name="lastName"
              type="text"
              autoComplete="family-name"
              required
              maxLength={100}
              value={lastName}
              onChange={(event) => setLastName(event.target.value)}
              aria-describedby={fieldErrors.lastName ? 'lastName-error' : undefined}
            />
            {fieldErrors.lastName && (
              <p id="lastName-error" className="field-error" role="alert">
                {fieldErrors.lastName}
              </p>
            )}
          </div>

          <div className="form-field">
            <label htmlFor="email">Adresse e-mail</label>
            <input
              id="email"
              name="email"
              type="email"
              autoComplete="email"
              required
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              aria-describedby={fieldErrors.email ? 'email-error' : undefined}
            />
            {fieldErrors.email && (
              <p id="email-error" className="field-error" role="alert">
                {fieldErrors.email}
              </p>
            )}
          </div>

          <div className="form-field">
            <label htmlFor="password">Mot de passe</label>
            <input
              id="password"
              name="password"
              type="password"
              autoComplete="new-password"
              required
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              aria-describedby={fieldErrors.password ? 'password-error' : undefined}
            />
            {fieldErrors.password && (
              <p id="password-error" className="field-error" role="alert">
                {fieldErrors.password}
              </p>
            )}
          </div>

          <Field label="Vous êtes">
            <div
              className="register-pill-row"
              role="group"
              aria-label="Vous êtes"
              aria-describedby={fieldErrors.role ? 'role-error' : undefined}
            >
              {ROLE_OPTIONS.map((option) => (
                <Pill
                  key={option.value}
                  label={option.label}
                  selected={role === option.value}
                  onClick={() => setRole(option.value)}
                  ariaLabel={`Rôle : ${option.label}`}
                />
              ))}
            </div>
            {fieldErrors.role && (
              <p id="role-error" className="field-error" role="alert">
                {fieldErrors.role}
              </p>
            )}
          </Field>

          {role === 'soignant' && (
            <div className="form-field">
              <label htmlFor="title">Titre (ex. Dr, Pr)</label>
              <input
                id="title"
                name="title"
                type="text"
                value={title}
                onChange={(event) => setTitle(event.target.value)}
              />
            </div>
          )}

          <div className="register-consent-field">
            <label htmlFor="consent" className="register-consent-label">
              <input
                id="consent"
                name="consent"
                type="checkbox"
                checked={consent}
                onChange={(event) => setConsent(event.target.checked)}
                aria-describedby={fieldErrors.consent ? 'consent-error' : undefined}
              />
              <span>
                J’accepte que mes données de santé soient traitées dans le cadre de MedLink.
              </span>
            </label>
            {fieldErrors.consent && (
              <p id="consent-error" className="field-error" role="alert">
                {fieldErrors.consent}
              </p>
            )}
          </div>

          {bannerError && (
            <p className="register-error" role="alert">
              {bannerError}
            </p>
          )}

          <button type="submit" className="register-submit" disabled={isSubmitting || !consent}>
            {isSubmitting ? 'Création du compte…' : 'Créer mon compte'}
          </button>
        </form>

        <p className="register-login-link">
          Déjà un compte ? <Link to="/login">Se connecter</Link>
        </p>
      </div>
    </main>
  );
}

function Field({ label, children }) {
  return (
    <div className="register-field">
      <span className="register-field-label">{label}</span>
      {children}
    </div>
  );
}

function Pill({ label, selected, onClick, ariaLabel }) {
  return (
    <button
      type="button"
      className={selected ? 'active' : undefined}
      aria-pressed={selected}
      aria-label={ariaLabel}
      onClick={onClick}
    >
      {label}
    </button>
  );
}

// Route un message d'erreur backend (POST /api/auth/register) vers le(s)
// champ(s) de formulaire concerné(s). fields: [] => bannière générique
// (429, réseau, message non reconnu).
function mapRegistrationErrorMessageToField(message) {
  if (!message) {
    return { fields: [], message };
  }

  // "Le champ "firstName" est obligatoire." -> la clé technique du payload
  // est déjà donnée entre guillemets par le backend, pas besoin de liste de
  // correspondances pour ce cas.
  const missingFieldMatch = message.match(/^Le champ "(\w+)" est obligatoire\.$/);
  if (missingFieldMatch) {
    return { fields: [missingFieldMatch[1]], message };
  }

  if (/rôle/i.test(message)) {
    return { fields: ['role'], message };
  }

  if (/email/i.test(message)) {
    return { fields: ['email'], message };
  }

  if (/mot de passe/i.test(message)) {
    return { fields: ['password'], message };
  }

  if (/consentement/i.test(message)) {
    return { fields: ['consent'], message };
  }

  if (/prénom et le nom/i.test(message)) {
    // Le backend ne précise pas lequel des deux dépasse la limite : on
    // associe le même message aux deux champs plutôt que de deviner.
    return { fields: ['firstName', 'lastName'], message };
  }

  // 429 rate-limit et tout ce qui n'est pas reconnu (erreur réseau, 500...).
  return { fields: [], message };
}
