import { createContext, useCallback, useContext, useMemo, useState } from 'react';
import { fetchContacts, fetchMessages } from '../services/messageService';
import { useAuth } from './AuthContext';

const MessagesBadgeContext = createContext(null);

// Compteur de messages non lus (tous contacts confondus) partagé entre le
// badge de l'icône "Messages" (BottomNav) et MessagesScreen : sans ce
// contexte commun, ouvrir une conversation ne mettrait à jour que la liste
// de contacts de l'écran, pas le badge affiché dans la barre de navigation.
// MessagesScreen a déjà calculé le statut non lu de chaque contact pour son
// propre affichage : elle pousse directement le total via
// setUnreadMessagesCount plutôt que de déclencher un second aller-retour
// réseau redondant avec refresh().
export function MessagesBadgeProvider({ children }) {
  const { roles, isAuthenticated } = useAuth();
  const [unreadMessagesCount, setUnreadMessagesCount] = useState(0);

  const refresh = useCallback(async () => {
    if (!isAuthenticated) return;

    try {
      const contacts = await fetchContacts(roles);
      const conversations = await Promise.all(contacts.map((contact) => fetchMessages(contact.id).catch(() => [])));
      const count = conversations.reduce(
        (total, messages, index) =>
          total + messages.filter((message) => message.senderId === contacts[index].id && !message.read).length,
        0,
      );
      setUnreadMessagesCount(count);
    } catch {
      // Le compteur reste tel quel : une erreur réseau ici ne doit pas
      // bloquer l'affichage du reste de l'écran.
    }
  }, [roles, isAuthenticated]);

  const value = useMemo(
    () => ({ unreadMessagesCount, refresh, setUnreadMessagesCount }),
    [unreadMessagesCount, refresh],
  );

  return <MessagesBadgeContext.Provider value={value}>{children}</MessagesBadgeContext.Provider>;
}

export function useMessagesBadge() {
  const context = useContext(MessagesBadgeContext);
  if (!context) {
    throw new Error('useMessagesBadge must be used within a MessagesBadgeProvider');
  }

  return context;
}
