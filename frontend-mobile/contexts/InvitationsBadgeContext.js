import { createContext, useCallback, useContext, useMemo, useState } from 'react';
import { fetchReceivedInvitations } from '../services/liaisonService';

const InvitationsBadgeContext = createContext(null);

// Compteur d'invitations en attente partagé entre le badge sur l'icône
// Profil (BottomNav) et l'écran InvitationsScreen : sans ce contexte commun,
// accepter/refuser une invitation ne mettrait à jour que la liste de l'écran,
// pas le badge affiché dans la barre de navigation.
export function InvitationsBadgeProvider({ children }) {
  const [pendingInvitationsCount, setPendingInvitationsCount] = useState(0);

  const refresh = useCallback(async () => {
    try {
      const invitations = await fetchReceivedInvitations();
      setPendingInvitationsCount(invitations.length);
    } catch {
      // Le badge reste tel quel : une erreur réseau ici ne doit pas
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

  return <InvitationsBadgeContext.Provider value={value}>{children}</InvitationsBadgeContext.Provider>;
}

export function useInvitationsBadge() {
  const context = useContext(InvitationsBadgeContext);
  if (!context) {
    throw new Error('useInvitationsBadge must be used within an InvitationsBadgeProvider');
  }

  return context;
}
