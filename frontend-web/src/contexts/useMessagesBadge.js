import { createContext, useContext } from 'react';

export const MessagesBadgeContext = createContext(null);

export function useMessagesBadge() {
  const context = useContext(MessagesBadgeContext);
  if (!context) {
    throw new Error('useMessagesBadge must be used within a MessagesBadgeProvider');
  }

  return context;
}
