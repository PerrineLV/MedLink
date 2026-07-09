import { useCallback, useEffect, useRef, useState } from 'react';
import { useFocusEffect, useNavigation, useRoute } from '@react-navigation/native';
import {
  ActivityIndicator,
  FlatList,
  KeyboardAvoidingView,
  Platform,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { fetchMessages, markMessageRead, sendMessage } from '../services/messageService';
import { COLORS, TYPE } from '../services/journalPresentation';

// Recommandation ML-26 (solo dev, délai limité) : polling plutôt que
// WebSocket/Mercure, largement suffisant pour l'usage visé.
const POLL_INTERVAL_MS = 12_000;
const GENERIC_LOAD_ERROR = 'Impossible de charger cette conversation. Vérifiez votre connexion.';
const GENERIC_SEND_ERROR = "Impossible d'envoyer ce message. Vérifiez votre connexion et réessayez.";

export default function ConversationScreen() {
  const navigation = useNavigation();
  const route = useRoute();
  const { contactId, firstName, lastName, role } = route.params;
  const contactName = `${firstName} ${lastName}`;

  const [messages, setMessages] = useState(null);
  const [error, setError] = useState(null);
  const [draft, setDraft] = useState('');
  const [isSending, setIsSending] = useState(false);
  const [sendError, setSendError] = useState(null);
  const listRef = useRef(null);

  const markConversationRead = useCallback(async (conversationMessages) => {
    const unread = conversationMessages.filter((message) => message.senderId === contactId && !message.read);
    if (unread.length === 0) return conversationMessages;

    try {
      const updated = await Promise.all(unread.map((message) => markMessageRead(message.id)));
      const updatedById = new Map(updated.map((message) => [message.id, message]));

      return conversationMessages.map((message) => updatedById.get(message.id) ?? message);
    } catch {
      // Effet de bord secondaire : un aléa réseau ici ne doit pas empêcher
      // d'afficher la conversation qui vient d'être chargée.
      return conversationMessages;
    }
  }, [contactId]);

  const load = useCallback(async () => {
    try {
      const fetched = await fetchMessages(contactId);
      setError(null);
      setMessages(await markConversationRead(fetched));
    } catch {
      setError(GENERIC_LOAD_ERROR);
    }
  }, [contactId, markConversationRead]);

  useFocusEffect(
    useCallback(() => {
      load();

      const interval = setInterval(load, POLL_INTERVAL_MS);
      return () => clearInterval(interval);
    }, [load]),
  );

  useEffect(() => {
    if (messages && messages.length > 0) {
      requestAnimationFrame(() => listRef.current?.scrollToEnd({ animated: true }));
    }
  }, [messages]);

  const handleSend = async () => {
    const content = draft.trim();
    if (!content || isSending) return;

    setSendError(null);
    setIsSending(true);

    try {
      const message = await sendMessage(contactId, content);
      setMessages((current) => [...(current ?? []), message]);
      setDraft('');
    } catch {
      // Le brouillon n'est pas effacé : pas de perte silencieuse du message
      // si l'envoi échoue (ex. coupure réseau).
      setSendError(GENERIC_SEND_ERROR);
    } finally {
      setIsSending(false);
    }
  };

  return (
    <KeyboardAvoidingView style={styles.flexFill} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <View style={styles.header}>
        <TouchableOpacity
          onPress={() => navigation.goBack()}
          accessibilityRole="button"
          accessibilityLabel="Retour"
          style={styles.backButton}
        >
          <Text style={styles.backButtonText}>‹ Retour</Text>
        </TouchableOpacity>
        <View style={styles.avatar} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
          <Text style={styles.avatarText}>{`${firstName[0] ?? ''}${lastName[0] ?? ''}`.toUpperCase()}</Text>
        </View>
        <Text style={styles.title}>{contactName}</Text>
      </View>

      {error && (
        <Text style={styles.error} accessibilityRole="alert">
          {error}
        </Text>
      )}

      {messages === null ? (
        <View style={[styles.flexFill, styles.centered]}>
          <ActivityIndicator color={COLORS.primary} size="large" />
        </View>
      ) : (
        <FlatList
          ref={listRef}
          style={styles.list}
          data={messages}
          keyExtractor={(message) => String(message.id)}
          contentContainerStyle={[styles.listContent, messages.length === 0 && styles.emptyList]}
          ListEmptyComponent={<Text style={styles.emptyText}>Aucun message pour le moment. Envoyez le premier !</Text>}
          renderItem={({ item }) => <MessageBubble message={item} contactId={contactId} contactName={contactName} />}
        />
      )}

      <View style={styles.composer}>
        <Text style={styles.composerLabel}>Votre message</Text>
        <View style={styles.composerRow}>
          <TextInput
            style={styles.composerInput}
            value={draft}
            onChangeText={setDraft}
            placeholder="Écrivez votre message…"
            multiline
            maxLength={2000}
            accessibilityLabel="Votre message"
          />
          <TouchableOpacity
            style={[styles.sendButton, (!draft.trim() || isSending) && styles.sendButtonDisabled]}
            onPress={handleSend}
            disabled={!draft.trim() || isSending}
            accessibilityRole="button"
            accessibilityLabel="Envoyer le message"
          >
            <Text style={styles.sendButtonText}>➤</Text>
          </TouchableOpacity>
        </View>
        {sendError && (
          <Text style={styles.error} accessibilityRole="alert">
            {sendError}
          </Text>
        )}
      </View>
    </KeyboardAvoidingView>
  );
}

function MessageBubble({ message, contactId, contactName }) {
  const isReceived = message.senderId === contactId;
  const time = new Date(message.createdAt).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
  const accessibilityLabel = isReceived
    ? `Message reçu de ${contactName} : ${message.content}`
    : `Message envoyé, ${message.read ? 'lu' : 'non lu'} : ${message.content}`;

  return (
    <View
      style={[styles.bubbleRow, isReceived ? styles.bubbleRowReceived : styles.bubbleRowSent]}
      accessible
      accessibilityRole="text"
      accessibilityLabel={accessibilityLabel}
    >
      <View style={[styles.bubble, isReceived ? styles.bubbleReceived : styles.bubbleSent]}>
        <Text style={isReceived ? styles.bubbleTextReceived : styles.bubbleTextSent}>{message.content}</Text>
        <Text style={[styles.bubbleMeta, isReceived ? styles.bubbleMetaReceived : styles.bubbleMetaSent]}>
          {time}
          {!isReceived && ` · ${message.read ? 'Lu' : 'Non lu'}`}
        </Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  flexFill: { flex: 1, backgroundColor: COLORS.background },
  centered: { justifyContent: 'center', alignItems: 'center' },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingTop: 56,
    paddingBottom: 16,
    paddingHorizontal: 20,
    backgroundColor: COLORS.surface,
  },
  backButton: { paddingVertical: 4 },
  backButtonText: { color: COLORS.primary, fontSize: TYPE.base, fontWeight: '600' },
  avatar: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: COLORS.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: { color: COLORS.onPrimary, fontWeight: '700', fontSize: TYPE.xs },
  title: { fontSize: TYPE.md, fontWeight: '700', color: COLORS.primary, flexShrink: 1 },
  error: {
    backgroundColor: COLORS.red.bg,
    color: COLORS.red.text,
    borderRadius: 16,
    padding: 12,
    margin: 20,
    marginBottom: 0,
    fontSize: TYPE.sm,
  },
  list: { flex: 1 },
  listContent: { padding: 20, gap: 8 },
  emptyList: { flexGrow: 1, justifyContent: 'center', alignItems: 'center' },
  emptyText: { color: COLORS.mutedText, fontSize: TYPE.sm, textAlign: 'center' },
  bubbleRow: { flexDirection: 'row', marginBottom: 8 },
  bubbleRowReceived: { justifyContent: 'flex-start' },
  bubbleRowSent: { justifyContent: 'flex-end' },
  bubble: { maxWidth: '75%', borderRadius: 16, paddingVertical: 10, paddingHorizontal: 14 },
  bubbleReceived: { backgroundColor: COLORS.mutedBackground },
  bubbleSent: { backgroundColor: COLORS.primary },
  bubbleTextReceived: { color: COLORS.primary, fontSize: TYPE.sm },
  bubbleTextSent: { color: COLORS.onPrimary, fontSize: TYPE.sm },
  bubbleMeta: { fontSize: TYPE.xs, marginTop: 4 },
  bubbleMetaReceived: { color: COLORS.mutedText },
  bubbleMetaSent: { color: 'rgba(255,255,255,0.75)' },
  composer: {
    borderTopWidth: 1,
    borderTopColor: COLORS.border,
    padding: 16,
    backgroundColor: COLORS.surface,
  },
  composerLabel: { fontSize: TYPE.xs, fontWeight: '700', color: COLORS.primary, marginBottom: 8 },
  composerRow: { flexDirection: 'row', alignItems: 'flex-end', gap: 8 },
  composerInput: {
    flex: 1,
    minHeight: 44,
    maxHeight: 120,
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 20,
    paddingHorizontal: 16,
    paddingVertical: 10,
    fontSize: TYPE.sm,
    color: COLORS.primary,
  },
  sendButton: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: COLORS.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  sendButtonDisabled: { opacity: 0.5 },
  sendButtonText: { color: COLORS.onPrimary, fontSize: TYPE.md },
});
