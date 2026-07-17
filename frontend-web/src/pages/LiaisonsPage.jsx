import { useCallback, useEffect, useRef, useState } from 'react';
import AppLayout from '../components/AppLayout';
import Badge from '../components/Badge';
import { fetchLiaisons, inviteLiaison, revokeLiaison } from '../services/liaisonService';
import { ROLE_LABELS, ROLE_SOIGNANT, formatSoignantName } from '../services/roles';
import './LiaisonsPage.css';

const GENERIC_INVITE_ERROR = "Impossible d'envoyer l'invitation, réessayez.";
const GENERIC_REVOKE_ERROR = 'Impossible de révoquer ce lien, réessayez.';

function initials(firstName, lastName) {
  return `${firstName[0] ?? ''}${lastName[0] ?? ''}`.toUpperCase();
}

function liaisonDisplayName(liaison) {
  return liaison.inviteeRole === ROLE_SOIGNANT
    ? formatSoignantName(liaison.inviteeFirstName, liaison.inviteeLastName, liaison.inviteeTitle)
    : `${liaison.inviteeFirstName} ${liaison.inviteeLastName}`;
}

export default function LiaisonsPage() {
  const [liaisons, setLiaisons] = useState(null);
  const [error, setError] = useState(null);
  const [pendingRevoke, setPendingRevoke] = useState(null);
  const listRef = useRef(null);

  const load = useCallback(async () => {
    setError(null);

    try {
      setLiaisons(await fetchLiaisons());
    } catch {
      setError('Impossible de charger vos liaisons. Vérifiez votre connexion.');
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const handleInvited = useCallback((liaison) => {
    setLiaisons((current) => [liaison, ...(current ?? [])]);
  }, []);

  const handleRevoked = useCallback((liaisonId) => {
    setLiaisons((current) => (current ?? []).filter((liaison) => liaison.id !== liaisonId));
    setPendingRevoke(null);
    listRef.current?.focus();
  }, []);

  const activeLiaisons = (liaisons ?? []).filter((liaison) => liaison.active);
  const pendingLiaisons = (liaisons ?? []).filter((liaison) => !liaison.active);

  return (
    <AppLayout>
      <h1 className="liaisons-title">Mes liaisons</h1>

      {error && (
        <p className="liaisons-error" role="alert">
          {error}
        </p>
      )}

      {!error && (
        <>
          <InviteForm onInvited={handleInvited} />

          {pendingRevoke && (
            <RevokeConfirmation
              liaison={pendingRevoke}
              onCancel={() => {
                setPendingRevoke(null);
                listRef.current?.focus();
              }}
              onRevoked={() => handleRevoked(pendingRevoke.id)}
            />
          )}

          {liaisons === null ? (
            <p className="liaisons-loading">Chargement…</p>
          ) : (
            <div className="liaisons-list" ref={listRef} tabIndex={-1}>
              <section aria-labelledby="liaisons-actives-heading">
                <h2 id="liaisons-actives-heading" className="liaisons-section-heading">
                  Actifs
                </h2>
                {activeLiaisons.length === 0 ? (
                  <p className="liaisons-empty">Aucun lien actif pour le moment.</p>
                ) : (
                  <ul className="liaison-list">
                    {activeLiaisons.map((liaison) => (
                      <ActiveLiaisonCard
                        key={liaison.id}
                        liaison={liaison}
                        onRevokeRequested={() => setPendingRevoke(liaison)}
                      />
                    ))}
                  </ul>
                )}
              </section>

              <section aria-labelledby="liaisons-en-attente-heading">
                <h2 id="liaisons-en-attente-heading" className="liaisons-section-heading">
                  En attente
                </h2>
                {pendingLiaisons.length === 0 ? (
                  <p className="liaisons-empty">Aucune invitation en attente.</p>
                ) : (
                  <ul className="liaison-list">
                    {pendingLiaisons.map((liaison) => (
                      <PendingLiaisonCard key={liaison.id} liaison={liaison} />
                    ))}
                  </ul>
                )}
              </section>
            </div>
          )}
        </>
      )}
    </AppLayout>
  );
}

function ActiveLiaisonCard({ liaison, onRevokeRequested }) {
  const name = liaisonDisplayName(liaison);
  const date = new Date(liaison.createdAt).toLocaleDateString('fr-FR');

  return (
    <li className="liaison-card">
      <span className="liaison-avatar" aria-hidden="true">
        {initials(liaison.inviteeFirstName, liaison.inviteeLastName)}
      </span>
      <span className="liaison-info">
        <span className="liaison-name">{name}</span>
        <span className="liaison-meta">
          {ROLE_LABELS[liaison.inviteeRole]} · rattaché·e le {date}
        </span>
      </span>
      <button
        type="button"
        className="liaison-revoke-button"
        onClick={onRevokeRequested}
        aria-label={`Révoquer l'accès de ${name}`}
      >
        Révoquer
      </button>
    </li>
  );
}

function PendingLiaisonCard({ liaison }) {
  const name = liaisonDisplayName(liaison);

  return (
    <li className="liaison-card">
      <span className="liaison-avatar" aria-hidden="true">
        {initials(liaison.inviteeFirstName, liaison.inviteeLastName)}
      </span>
      <span className="liaison-info">
        <span className="liaison-name">{name}</span>
        <span className="liaison-meta">{ROLE_LABELS[liaison.inviteeRole]}</span>
      </span>
      <Badge level="orange" label="En attente" />
    </li>
  );
}

function RevokeConfirmation({ liaison, onCancel, onRevoked }) {
  const [isRevoking, setIsRevoking] = useState(false);
  const [error, setError] = useState(null);
  const name = liaisonDisplayName(liaison);

  const handleConfirm = async () => {
    setError(null);
    setIsRevoking(true);

    try {
      await revokeLiaison(liaison.id);
      onRevoked();
    } catch (requestError) {
      setError(requestError.response?.data?.detail ?? GENERIC_REVOKE_ERROR);
      setIsRevoking(false);
    }
  };

  return (
    <div
      className="liaisons-confirm"
      role="alertdialog"
      aria-live="assertive"
      aria-label="Confirmer la révocation"
    >
      <p>Révoquer l&apos;accès de {name} ? Il/elle ne pourra plus consulter votre suivi.</p>
      {error && (
        <p className="liaisons-error" role="alert">
          {error}
        </p>
      )}
      <div className="liaisons-confirm-actions">
        <button
          type="button"
          className="liaisons-confirm-button"
          onClick={handleConfirm}
          disabled={isRevoking}
        >
          {isRevoking ? 'Révocation…' : 'Confirmer'}
        </button>
        <button
          type="button"
          className="liaisons-cancel-button"
          onClick={onCancel}
          disabled={isRevoking}
        >
          Annuler
        </button>
      </div>
    </div>
  );
}

function InviteForm({ onInvited }) {
  const [email, setEmail] = useState('');
  const [error, setError] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async (event) => {
    event.preventDefault();
    setError(null);
    setIsSubmitting(true);

    try {
      const liaison = await inviteLiaison(email.trim());
      onInvited(liaison);
      setEmail('');
    } catch (requestError) {
      setError(requestError.response?.data?.detail ?? GENERIC_INVITE_ERROR);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <form className="liaisons-invite" onSubmit={handleSubmit}>
      <label className="liaisons-invite-label" htmlFor="liaisons-invite-email">
        Inviter un aidant ou un soignant
      </label>
      <div className="liaisons-invite-row">
        <input
          id="liaisons-invite-email"
          type="email"
          required
          placeholder="email@exemple.fr"
          value={email}
          onChange={(event) => setEmail(event.target.value)}
        />
        <button type="submit" disabled={isSubmitting}>
          {isSubmitting ? 'Envoi…' : 'Inviter'}
        </button>
      </div>
      {error && (
        <p className="liaisons-error" role="alert">
          {error}
        </p>
      )}
    </form>
  );
}
