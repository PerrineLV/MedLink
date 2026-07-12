import { useCallback, useMemo, useState } from 'react';
import { fetchReceivedInvitations } from '../services/liaisonService';
import { InvitationsBadgeContext } from './useInvitationsBadge';

// Compteur d'invitations en attente partagé entre la cloche/le badge sidebar
// de l'en-tête (AppLayout) et l'écran InvitationsPage : sans ce contexte
// commun, accepter/refuser une invitation ne mettrait à jour que la liste de
// la page, pas le badge affiché ailleurs dans le même AppLayout.
export function InvitationsBadgeProvider({ children }) {
  const [pendingInvitationsCount, setPendingInvitationsCount] = useState(0);

  const refresh = useCallback(async () => {
    try {
      const invitations = await fetchReceivedInvitations();
      setPendingInvitationsCount(invitations.length);
    } catch {
      // Le compteur reste tel quel : une erreur réseau ici ne doit pas
      // bloquer l'affichage du reste de l'écran.
    }
  }, []);

  const decrement = useCallback(() => {
    setPendingInvitationsCount((current) => Math.max(0, current - 1));
  }, []);

  const value = useMemo(
    () => ({ pendingInvitationsCount, refresh, decrement }),
    [pendingInvitationsCount, refresh, decrement],
  );

  return (
    <InvitationsBadgeContext.Provider value={value}>{children}</InvitationsBadgeContext.Provider>
  );
}
