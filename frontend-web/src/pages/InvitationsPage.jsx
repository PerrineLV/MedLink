import { useCallback, useEffect, useRef, useState } from 'react';
import AppLayout from '../components/AppLayout';
import { useInvitationsBadge } from '../contexts/InvitationsBadgeContext';
import {
  acceptInvitation,
  fetchReceivedInvitations,
  rejectInvitation,
} from '../services/liaisonService';
import { ROLE_LABELS } from '../services/roles';
import './InvitationsPage.css';

const SECURITY_BANNER_TEXT = 'Données chiffrées - accès soignants uniquement';
const GENERIC_LOAD_ERROR = 'Impossible de charger vos invitations. Vérifiez votre connexion.';
const GENERIC_RESPOND_ERROR = 'Impossible de traiter cette invitation, réessayez.';

function initials(firstName, lastName) {
  return `${firstName[0] ?? ''}${lastName[0] ?? ''}`.toUpperCase();
}

export default function InvitationsPage() {
  const [invitations, setInvitations] = useState(null);
  const [error, setError] = useState(null);
  const listRef = useRef(null);
  const { decrement: decrementPendingInvitationsCount } = useInvitationsBadge();

  const load = useCallback(async () => {
    setError(null);

    try {
      setInvitations(await fetchReceivedInvitations());
    } catch {
      setError(GENERIC_LOAD_ERROR);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const handleResolved = useCallback(
    (invitationId) => {
      setInvitations((current) =>
        (current ?? []).filter((invitation) => invitation.id !== invitationId),
      );
      decrementPendingInvitationsCount();
      listRef.current?.focus();
    },
    [decrementPendingInvitationsCount],
  );

  return (
    <AppLayout securityBanner={SECURITY_BANNER_TEXT}>
      <h1 className="invitations-title">Invitations en attente</h1>

      {error && (
        <p className="invitations-error" role="alert">
          {error}
        </p>
      )}

      {!error &&
        (invitations === null ? (
          <p className="invitations-loading">Chargement…</p>
        ) : invitations.length === 0 ? (
          <p className="invitations-empty">Aucune invitation en attente.</p>
        ) : (
          <ul className="invitation-list" ref={listRef} tabIndex={-1}>
            {invitations.map((invitation) => (
              <InvitationCard
                key={invitation.id}
                invitation={invitation}
                onResolved={handleResolved}
              />
            ))}
          </ul>
        ))}
    </AppLayout>
  );
}

function InvitationCard({ invitation, onResolved }) {
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState(null);
  const name = `${invitation.patientFirstName} ${invitation.patientLastName}`;

  const respond = async (action) => {
    setError(null);
    setIsSubmitting(true);

    try {
      await action(invitation.id);
      onResolved(invitation.id);
    } catch (requestError) {
      setError(requestError.response?.data?.detail ?? GENERIC_RESPOND_ERROR);
      setIsSubmitting(false);
    }
  };

  return (
    <li className="invitation-card">
      <span className="invitation-avatar" aria-hidden="true">
        {initials(invitation.patientFirstName, invitation.patientLastName)}
      </span>
      <span className="invitation-info">
        <span className="invitation-name">{name}</span>
        <span className="invitation-meta">
          souhaite vous ajouter comme {ROLE_LABELS[invitation.inviteeRole]}
        </span>
        {error && (
          <span className="invitations-error" role="alert">
            {error}
          </span>
        )}
      </span>
      <span className="invitation-actions">
        <button
          type="button"
          className="invitation-accept-button"
          onClick={() => respond(acceptInvitation)}
          disabled={isSubmitting}
          aria-label={`Accepter l'invitation de ${name}`}
        >
          Accepter
        </button>
        <button
          type="button"
          className="invitation-reject-button"
          onClick={() => respond(rejectInvitation)}
          disabled={isSubmitting}
          aria-label={`Refuser l'invitation de ${name}`}
        >
          Refuser
        </button>
      </span>
    </li>
  );
}
