import { useCallback, useEffect, useRef, useState } from 'react';
import { Send } from 'lucide-react';
import AppLayout from '../components/AppLayout';
import { useMessagesBadge } from '../contexts/useMessagesBadge';
import {
  fetchContacts,
  fetchMessages,
  markMessageRead,
  sendMessage,
} from '../services/messageService';
import { ROLE_LABELS } from '../services/roles';
import './MessagingPage.css';

// Recommandation ML-26 (solo dev, délai limité) : polling plutôt que
// WebSocket/Mercure, largement suffisant pour l'usage visé.
const POLL_INTERVAL_MS = 12_000;
const GENERIC_CONTACTS_ERROR = 'Impossible de charger vos contacts. Vérifiez votre connexion.';
const GENERIC_MESSAGES_ERROR =
  'Impossible de charger cette conversation. Vérifiez votre connexion.';
const GENERIC_SEND_ERROR =
  "Impossible d'envoyer ce message. Vérifiez votre connexion et réessayez.";

function initials(firstName, lastName) {
  return `${firstName[0] ?? ''}${lastName[0] ?? ''}`.toUpperCase();
}

// Précise, pour un contact aidant/soignant, via quel(s) patient(s) commun(s)
// l'échange est autorisé (ML-70) : indispensable quand on a plusieurs
// contacts de ce type (ex. un soignant avec plusieurs aidants) et qu'on ne
// sait pas sinon lequel correspond à quel patient.
function viaPatientsLabel(viaPatients) {
  if (!viaPatients || viaPatients.length === 0) return null;

  return `via ${viaPatients.map((patient) => `${patient.firstName} ${patient.lastName}`).join(', ')}`;
}

function ContactListItem({ contact, isSelected, onSelect }) {
  const via = viaPatientsLabel(contact.viaPatients);

  return (
    <li>
      <button
        type="button"
        className={isSelected ? 'messaging-contact active' : 'messaging-contact'}
        onClick={onSelect}
        aria-current={isSelected}
      >
        <span className="messaging-contact-avatar" aria-hidden="true">
          {initials(contact.firstName, contact.lastName)}
        </span>
        <span className="messaging-contact-info">
          <span className="messaging-contact-name">
            {contact.firstName} {contact.lastName}
          </span>
          <span className="messaging-contact-role">{ROLE_LABELS[contact.role]}</span>
          {via && (
            <span className="messaging-contact-via" title={via}>
              {via}
            </span>
          )}
          {contact.hasUnread && (
            <span className="messaging-contact-unread" aria-label="Message non lu">
              Non lu
            </span>
          )}
        </span>
      </button>
    </li>
  );
}

export default function MessagingPage() {
  const { setUnreadMessagesCount } = useMessagesBadge();
  const [contacts, setContacts] = useState(null);
  const [contactsError, setContactsError] = useState(null);
  const [selectedContactId, setSelectedContactId] = useState(null);
  const [messages, setMessages] = useState(null);
  const [messagesError, setMessagesError] = useState(null);

  // Un message reçu (senderId = ce contact) et pas encore lu est marqué lu
  // dès qu'il est affiché : c'est la conversation ouverte qui vaut lecture,
  // pas un geste explicite de l'utilisateur.
  const markConversationRead = useCallback(async (conversationMessages, contactId) => {
    const unread = conversationMessages.filter(
      (message) => message.senderId === contactId && !message.read,
    );
    if (unread.length === 0) return conversationMessages;

    try {
      const updated = await Promise.all(unread.map((message) => markMessageRead(message.id)));
      const updatedById = new Map(updated.map((message) => [message.id, message]));

      setContacts((current) =>
        (current ?? []).map((contact) =>
          contact.id === contactId ? { ...contact, hasUnread: false } : contact,
        ),
      );

      return conversationMessages.map((message) => updatedById.get(message.id) ?? message);
    } catch {
      // Effet de bord secondaire : un aléa réseau ici ne doit pas empêcher
      // d'afficher la conversation qui vient d'être chargée.
      return conversationMessages;
    }
  }, []);

  const loadConversation = useCallback(
    async (contactId) => {
      try {
        const fetched = await fetchMessages(contactId);
        setMessagesError(null);
        setMessages(await markConversationRead(fetched, contactId));
      } catch {
        setMessagesError(GENERIC_MESSAGES_ERROR);
      }
    },
    [markConversationRead],
  );

  const loadContacts = useCallback(async () => {
    setContactsError(null);

    try {
      const fetchedContacts = await fetchContacts();
      const withUnreadFlag = await Promise.all(
        fetchedContacts.map(async (contact) => {
          const conversation = await fetchMessages(contact.id).catch(() => []);
          const hasUnread = conversation.some(
            (message) => message.senderId === contact.id && !message.read,
          );

          return { ...contact, hasUnread };
        }),
      );
      setContacts(withUnreadFlag);
    } catch {
      setContactsError(GENERIC_CONTACTS_ERROR);
    }
  }, []);

  useEffect(() => {
    loadContacts();
  }, [loadContacts]);

  // Cette page a déjà calculé le statut non lu de chaque contact pour son
  // propre affichage : on le pousse au badge partagé plutôt que de
  // déclencher un second aller-retour réseau redondant (cf. refresh() du
  // contexte, utilisé par les autres pages).
  useEffect(() => {
    if (contacts) {
      setUnreadMessagesCount(contacts.filter((contact) => contact.hasUnread).length);
    }
  }, [contacts, setUnreadMessagesCount]);

  useEffect(() => {
    if (!selectedContactId) return undefined;

    setMessages(null);
    loadConversation(selectedContactId);

    const interval = setInterval(() => loadConversation(selectedContactId), POLL_INTERVAL_MS);

    return () => clearInterval(interval);
  }, [selectedContactId, loadConversation]);

  const handleSent = useCallback((message) => {
    setMessages((current) => [...(current ?? []), message]);
  }, []);

  const selectedContact =
    (contacts ?? []).find((contact) => contact.id === selectedContactId) ?? null;

  return (
    <AppLayout>
      <h1 className="messaging-title">Messagerie</h1>

      {contactsError && (
        <p className="messaging-error" role="alert">
          {contactsError}
        </p>
      )}

      {!contactsError && (
        <div className="messaging-layout">
          <nav className="messaging-contacts" aria-label="Contacts">
            {contacts === null ? (
              <p className="messaging-loading">Chargement…</p>
            ) : contacts.length === 0 ? (
              <p className="messaging-empty">Aucun contact disponible.</p>
            ) : (
              <ul>
                {contacts.map((contact) => (
                  <ContactListItem
                    key={contact.id}
                    contact={contact}
                    isSelected={contact.id === selectedContactId}
                    onSelect={() => setSelectedContactId(contact.id)}
                  />
                ))}
              </ul>
            )}
          </nav>

          <div className="messaging-thread">
            {selectedContact ? (
              <ConversationThread
                key={selectedContact.id}
                contact={selectedContact}
                messages={messages}
                error={messagesError}
                onSent={handleSent}
              />
            ) : (
              contacts !== null &&
              contacts.length > 0 && (
                <p className="messaging-empty">
                  Sélectionnez un contact pour afficher la conversation.
                </p>
              )
            )}
          </div>
        </div>
      )}
    </AppLayout>
  );
}

function ConversationThread({ contact, messages, error, onSent }) {
  const [draft, setDraft] = useState('');
  const [sendError, setSendError] = useState(null);
  const [isSending, setIsSending] = useState(false);
  const scrollRef = useRef(null);

  useEffect(() => {
    if (scrollRef.current) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
    }
  }, [messages]);

  const handleSubmit = async (event) => {
    event.preventDefault();
    const content = draft.trim();
    if (!content || isSending) return;

    setSendError(null);
    setIsSending(true);

    try {
      const message = await sendMessage(contact.id, content);
      onSent(message);
      setDraft('');
    } catch {
      // Le brouillon n'est pas effacé : l'utilisateur ne doit pas perdre
      // silencieusement ce qu'il a écrit si l'envoi échoue.
      setSendError(GENERIC_SEND_ERROR);
    } finally {
      setIsSending(false);
    }
  };

  const contactName = `${contact.firstName} ${contact.lastName}`;

  return (
    <>
      <div className="messaging-thread-header">
        <span className="messaging-contact-avatar" aria-hidden="true">
          {initials(contact.firstName, contact.lastName)}
        </span>
        <span className="messaging-contact-name">{contactName}</span>
      </div>

      {error && (
        <p className="messaging-error" role="alert">
          {error}
        </p>
      )}

      <div className="messaging-bubbles-container" ref={scrollRef}>
        {messages === null ? (
          <p className="messaging-loading">Chargement…</p>
        ) : messages.length === 0 ? (
          <p className="messaging-empty">Aucun message pour le moment. Envoyez le premier !</p>
        ) : (
          <ul className="messaging-bubbles">
            {messages.map((message) => (
              <MessageBubble
                key={message.id}
                message={message}
                contactId={contact.id}
                contactName={contactName}
              />
            ))}
          </ul>
        )}
      </div>

      <form className="messaging-composer" onSubmit={handleSubmit}>
        <label htmlFor="messaging-input" className="messaging-composer-label">
          Votre message
        </label>
        <div className="messaging-composer-row">
          <input
            id="messaging-input"
            type="text"
            value={draft}
            onChange={(event) => setDraft(event.target.value)}
            maxLength={2000}
            placeholder="Écrivez votre message…"
            autoComplete="off"
          />
          <button
            type="submit"
            className="messaging-send-button"
            disabled={!draft.trim() || isSending}
            aria-label="Envoyer le message"
          >
            <Send aria-hidden="true" size={18} />
          </button>
        </div>
        {sendError && (
          <p className="messaging-error" role="alert">
            {sendError}
          </p>
        )}
      </form>
    </>
  );
}

function MessageBubble({ message, contactId, contactName }) {
  const isReceived = message.senderId === contactId;
  const time = new Date(message.createdAt).toLocaleTimeString('fr-FR', {
    hour: '2-digit',
    minute: '2-digit',
  });
  const accessibleLabel = isReceived
    ? `Message reçu de ${contactName} : ${message.content}`
    : `Message envoyé, ${message.read ? 'lu' : 'non lu'} : ${message.content}`;

  return (
    <li
      className={isReceived ? 'messaging-bubble-row received' : 'messaging-bubble-row sent'}
      aria-label={accessibleLabel}
    >
      <div className="messaging-bubble" aria-hidden="true">
        <p className="messaging-bubble-content">{message.content}</p>
        <span className="messaging-bubble-meta">
          {time}
          {!isReceived && ` · ${message.read ? 'Lu' : 'Non lu'}`}
        </span>
      </div>
    </li>
  );
}
