import { useEffect, useRef, useState } from 'react';
import { Download, KeyRound, Mail, ShieldCheck, Trash2, User } from 'lucide-react';
import AppLayout from '../components/AppLayout';
import { useAuth } from '../contexts/AuthContext';
import {
  changeEmail,
  changePassword,
  deleteAccount,
  downloadAccountExport,
  fetchMe,
} from '../services/accountService';
import { ROLE_LABELS, getPrimaryRole } from '../services/roles';
import './AccountPage.css';

const GENERIC_LOAD_ERROR = 'Impossible de charger vos informations. Vérifiez votre connexion.';
const GENERIC_EMAIL_ERROR = "Impossible de changer l'adresse e-mail, réessayez.";
const GENERIC_PASSWORD_ERROR = 'Impossible de changer le mot de passe, réessayez.';
const GENERIC_EXPORT_ERROR = 'Impossible de générer votre export, réessayez.';
const GENERIC_DELETE_ERROR = 'Impossible de supprimer votre compte, réessayez.';

function formatDate(isoDate) {
  return new Date(isoDate).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
}

// Route un message d'erreur backend (PATCH /api/me/password, ML-67) vers le
// champ concerné. Miroir des mappers d'inscription (RegisterPage.jsx),
// dupliqué volontairement comme le reste des services de ce projet.
function mapPasswordErrorMessageToField(message) {
  if (!message) {
    return { fields: [], message };
  }

  const missingFieldMatch = message.match(/^Le champ "(\w+)" est obligatoire\.$/);
  if (missingFieldMatch) {
    return { fields: [missingFieldMatch[1]], message };
  }

  if (/actuel incorrect/i.test(message)) {
    return { fields: ['currentPassword'], message };
  }

  if (/mot de passe doit contenir/i.test(message)) {
    return { fields: ['newPassword'], message };
  }

  return { fields: [], message };
}

// Miroir de mapPasswordErrorMessageToField pour PATCH /api/me/email (ML-67).
function mapEmailErrorMessageToField(message) {
  if (!message) {
    return { fields: [], message };
  }

  const missingFieldMatch = message.match(/^Le champ "(\w+)" est obligatoire\.$/);
  if (missingFieldMatch) {
    return { fields: [missingFieldMatch[1]], message };
  }

  if (/incorrect/i.test(message)) {
    return { fields: ['password'], message };
  }

  if (/email invalide|déjà utilisé/i.test(message)) {
    return { fields: ['newEmail'], message };
  }

  return { fields: [], message };
}

export default function AccountPage() {
  const { logout } = useAuth();

  const [me, setMe] = useState(null);
  const [loadError, setLoadError] = useState(null);

  useEffect(() => {
    let cancelled = false;

    fetchMe()
      .then((data) => {
        if (!cancelled) setMe(data);
      })
      .catch(() => {
        if (!cancelled) setLoadError(GENERIC_LOAD_ERROR);
      });

    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <AppLayout>
      <h1 className="account-title">Mon compte</h1>

      {loadError && (
        <p className="account-error" role="alert">
          {loadError}
        </p>
      )}

      {!loadError && !me && <p className="account-loading">Chargement…</p>}

      {me && (
        <>
          <div className="account-row">
            <MyInformationSection me={me} />
            <RgpdSection />
          </div>
          <div className="account-row">
            <ChangeEmailSection onEmailChanged={logout} />
            <ChangePasswordSection />
          </div>
          <div className="account-row">
            <ExportSection />
            <DeleteAccountSection onDeleted={logout} />
          </div>
        </>
      )}
    </AppLayout>
  );
}

function MyInformationSection({ me }) {
  const primaryRole = getPrimaryRole(me.roles);

  return (
    <section className="account-section" aria-labelledby="account-info-heading">
      <h2 id="account-info-heading" className="account-section-heading">
        <User aria-hidden="true" size={20} />
        Mes informations
      </h2>
      <dl className="account-info-list">
        <div className="account-info-row">
          <dt>Adresse e-mail</dt>
          <dd>{me.email}</dd>
        </div>
        <div className="account-info-row">
          <dt>Prénom</dt>
          <dd>{me.firstName}</dd>
        </div>
        <div className="account-info-row">
          <dt>Nom</dt>
          <dd>{me.lastName}</dd>
        </div>
        <div className="account-info-row">
          <dt>Rôle</dt>
          <dd>{primaryRole ? ROLE_LABELS[primaryRole] : '—'}</dd>
        </div>
        <div className="account-info-row">
          <dt>Compte créé le</dt>
          <dd>{formatDate(me.createdAt)}</dd>
        </div>
      </dl>
    </section>
  );
}

function ChangeEmailSection({ onEmailChanged }) {
  const [newEmail, setNewEmail] = useState('');
  const [password, setPassword] = useState('');
  const [fieldErrors, setFieldErrors] = useState({});
  const [bannerError, setBannerError] = useState(null);
  const [success, setSuccess] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async (event) => {
    event.preventDefault();
    setFieldErrors({});
    setBannerError(null);
    setIsSubmitting(true);

    try {
      await changeEmail({ password, newEmail });
      setSuccess(true);
      // Le token en cours est lié à l'ancien e-mail (username du JWT) : il
      // devient invalide dès la prochaine requête. On force la
      // déconnexion, en laissant le message ci-dessous le temps de
      // s'afficher plutôt que de rediriger dans le même rendu.
      setTimeout(onEmailChanged, 1500);
    } catch (requestError) {
      const responseMessage = requestError.response?.data?.message;
      const { fields, message } = mapEmailErrorMessageToField(responseMessage);

      if (fields.length > 0) {
        const nextFieldErrors = {};
        fields.forEach((field) => {
          nextFieldErrors[field] = message;
        });
        setFieldErrors(nextFieldErrors);
      } else {
        setBannerError(message ?? GENERIC_EMAIL_ERROR);
      }
      setIsSubmitting(false);
    }
  };

  return (
    <section className="account-section" aria-labelledby="account-email-heading">
      <h2 id="account-email-heading" className="account-section-heading">
        <Mail aria-hidden="true" size={20} />
        Changer mon adresse e-mail
      </h2>

      <form className="account-password-form" onSubmit={handleSubmit} noValidate>
        <div className="form-field">
          <label htmlFor="newEmail">Nouvelle adresse e-mail</label>
          <input
            id="newEmail"
            name="newEmail"
            type="email"
            autoComplete="email"
            required
            disabled={success}
            value={newEmail}
            onChange={(event) => setNewEmail(event.target.value)}
            aria-describedby={fieldErrors.newEmail ? 'newEmail-error' : undefined}
          />
          {fieldErrors.newEmail && (
            <p id="newEmail-error" className="field-error" role="alert">
              {fieldErrors.newEmail}
            </p>
          )}
        </div>

        <div className="form-field">
          <label htmlFor="email-password">Mot de passe actuel</label>
          <input
            id="email-password"
            name="password"
            type="password"
            autoComplete="current-password"
            required
            disabled={success}
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            aria-describedby={fieldErrors.password ? 'email-password-error' : undefined}
          />
          {fieldErrors.password && (
            <p id="email-password-error" className="field-error" role="alert">
              {fieldErrors.password}
            </p>
          )}
        </div>

        {bannerError && (
          <p className="account-error" role="alert">
            {bannerError}
          </p>
        )}

        {success && (
          <p className="account-success" role="status">
            Adresse e-mail mise à jour. Vous allez être déconnecté·e pour vous reconnecter avec
            votre nouvel e-mail.
          </p>
        )}

        <button type="submit" className="account-button" disabled={isSubmitting || success}>
          {isSubmitting ? 'Mise à jour…' : 'Changer mon adresse e-mail'}
        </button>
      </form>
    </section>
  );
}

function RgpdSection() {
  return (
    <section className="account-section" aria-labelledby="account-rgpd-heading">
      <h2 id="account-rgpd-heading" className="account-section-heading">
        <ShieldCheck aria-hidden="true" size={20} />
        Vos droits (RGPD)
      </h2>
      <p className="account-rgpd-text">
        MedLink traite vos données de suivi médical (journal, messages, rendez-vous) dans le seul
        but de coordonner votre parcours de soin entre patient, aidant et soignant. Cette base
        légale repose sur le consentement explicite que vous avez donné à l’inscription de votre
        compte. Vos données sont conservées pendant toute la durée d’utilisation du compte, et
        chiffrées au repos et en transit. Vous pouvez à tout moment consulter, exporter ou supprimer
        vos données personnelles depuis cette page.
      </p>
    </section>
  );
}

function ChangePasswordSection() {
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmNewPassword, setConfirmNewPassword] = useState('');
  const [fieldErrors, setFieldErrors] = useState({});
  const [bannerError, setBannerError] = useState(null);
  const [success, setSuccess] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async (event) => {
    event.preventDefault();
    setFieldErrors({});
    setBannerError(null);
    setSuccess(false);

    if (newPassword !== confirmNewPassword) {
      setFieldErrors({ confirmNewPassword: 'Les mots de passe ne correspondent pas.' });
      return;
    }

    setIsSubmitting(true);

    try {
      await changePassword({ currentPassword, newPassword });
      setCurrentPassword('');
      setNewPassword('');
      setConfirmNewPassword('');
      setSuccess(true);
    } catch (requestError) {
      const responseMessage = requestError.response?.data?.message;
      const { fields, message } = mapPasswordErrorMessageToField(responseMessage);

      if (fields.length > 0) {
        const nextFieldErrors = {};
        fields.forEach((field) => {
          nextFieldErrors[field] = message;
        });
        setFieldErrors(nextFieldErrors);
      } else {
        setBannerError(message ?? GENERIC_PASSWORD_ERROR);
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <section className="account-section" aria-labelledby="account-password-heading">
      <h2 id="account-password-heading" className="account-section-heading">
        <KeyRound aria-hidden="true" size={20} />
        Changer mon mot de passe
      </h2>

      <form className="account-password-form" onSubmit={handleSubmit} noValidate>
        <div className="form-field">
          <label htmlFor="currentPassword">Mot de passe actuel</label>
          <input
            id="currentPassword"
            name="currentPassword"
            type="password"
            autoComplete="current-password"
            required
            value={currentPassword}
            onChange={(event) => setCurrentPassword(event.target.value)}
            aria-describedby={fieldErrors.currentPassword ? 'currentPassword-error' : undefined}
          />
          {fieldErrors.currentPassword && (
            <p id="currentPassword-error" className="field-error" role="alert">
              {fieldErrors.currentPassword}
            </p>
          )}
        </div>

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
          <p className="account-error" role="alert">
            {bannerError}
          </p>
        )}

        {success && (
          <p className="account-success" role="status">
            Mot de passe mis à jour.
          </p>
        )}

        <button type="submit" className="account-button" disabled={isSubmitting}>
          {isSubmitting ? 'Mise à jour…' : 'Changer mon mot de passe'}
        </button>
      </form>
    </section>
  );
}

function ExportSection() {
  const [isExporting, setIsExporting] = useState(false);
  const [error, setError] = useState(null);

  const handleExport = async () => {
    setError(null);
    setIsExporting(true);

    try {
      await downloadAccountExport();
    } catch {
      setError(GENERIC_EXPORT_ERROR);
    } finally {
      setIsExporting(false);
    }
  };

  return (
    <section className="account-section" aria-labelledby="account-export-heading">
      <h2 id="account-export-heading" className="account-section-heading">
        Télécharger mes données
      </h2>
      <p className="account-section-description">
        Recevez une copie de vos données personnelles au format JSON (droit à la portabilité).
      </p>

      {error && (
        <p className="account-error" role="alert">
          {error}
        </p>
      )}

      <button
        type="button"
        className="account-button"
        onClick={handleExport}
        disabled={isExporting}
      >
        <Download aria-hidden="true" size={18} />
        {isExporting ? 'Génération…' : 'Télécharger mes données'}
      </button>
    </section>
  );
}

function DeleteAccountSection({ onDeleted }) {
  const [isConfirming, setIsConfirming] = useState(false);
  const [password, setPassword] = useState('');
  const [error, setError] = useState(null);
  const [isDeleting, setIsDeleting] = useState(false);
  const openButtonRef = useRef(null);

  const closeConfirmation = () => {
    setIsConfirming(false);
    setPassword('');
    setError(null);
    openButtonRef.current?.focus();
  };

  const handleDelete = async (event) => {
    event.preventDefault();
    setError(null);
    setIsDeleting(true);

    try {
      await deleteAccount({ password });
      onDeleted();
    } catch (requestError) {
      setError(requestError.response?.data?.message ?? GENERIC_DELETE_ERROR);
      setIsDeleting(false);
    }
  };

  return (
    <section className="account-section" aria-labelledby="account-delete-heading">
      <h2 id="account-delete-heading" className="account-section-heading">
        <Trash2 aria-hidden="true" size={20} />
        Supprimer mon compte
      </h2>
      <p className="account-section-description">
        Cette action anonymise définitivement votre compte : elle ne peut pas être annulée.
      </p>

      {!isConfirming && (
        <button
          type="button"
          ref={openButtonRef}
          className="account-delete-button"
          onClick={() => setIsConfirming(true)}
        >
          Supprimer mon compte
        </button>
      )}

      {isConfirming && (
        <form
          className="account-delete-confirm"
          role="alertdialog"
          aria-label="Confirmer la suppression du compte"
          onSubmit={handleDelete}
        >
          <p>
            Pour confirmer la suppression définitive de votre compte, saisissez votre mot de passe.
          </p>

          <div className="form-field">
            <label htmlFor="delete-password">Mot de passe</label>
            <input
              id="delete-password"
              name="password"
              type="password"
              autoComplete="current-password"
              required
              autoFocus
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              aria-describedby={error ? 'delete-password-error' : undefined}
            />
          </div>

          {error && (
            <p id="delete-password-error" className="field-error" role="alert">
              {error}
            </p>
          )}

          <div className="account-delete-confirm-actions">
            <button type="submit" className="account-delete-button" disabled={isDeleting}>
              {isDeleting ? 'Suppression…' : 'Confirmer la suppression'}
            </button>
            <button
              type="button"
              className="account-cancel-button"
              onClick={closeConfirmation}
              disabled={isDeleting}
            >
              Annuler
            </button>
          </div>
        </form>
      )}
    </section>
  );
}
