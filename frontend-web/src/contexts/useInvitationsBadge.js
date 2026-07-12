import { createContext, useContext } from 'react';

export const InvitationsBadgeContext = createContext(null);

export function useInvitationsBadge() {
  const context = useContext(InvitationsBadgeContext);
  if (!context) {
    throw new Error('useInvitationsBadge must be used within an InvitationsBadgeProvider');
  }

  return context;
}
