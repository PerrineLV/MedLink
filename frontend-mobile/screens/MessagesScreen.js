import { useCallback, useEffect, useState } from 'react';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import { ActivityIndicator, RefreshControl, ScrollView, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import BottomNav, { openProfileMenu } from '../components/BottomNav';
import Header from '../components/Header';
import SecurityBanner from '../components/SecurityBanner';
import { useAuth } from '../contexts/AuthContext';
import { useMessagesBadge } from '../contexts/MessagesBadgeContext';
import { fetchContacts, fetchMessages } from '../services/messageService';
import { COLORS, TYPE } from '../services/journalPresentation';
import { ROLE_LABELS, getPrimaryRole } from '../services/roles';

const MIN_TOUCH_TARGET = 44;
const GENERIC_LOAD_ERROR = 'Impossible de charger vos contacts. Vérifiez votre connexion.';

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

export default function MessagesScreen() {
  const navigation = useNavigation();
  const { firstName, roles, logout } = useAuth();
  const primaryRole = getPrimaryRole(roles);
  const displayName = firstName ?? (primaryRole ? ROLE_LABELS[primaryRole] : 'Utilisateur');
  const { setUnreadMessagesCount } = useMessagesBadge();

  const [contacts, setContacts] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [error, setError] = useState(null);

  const load = useCallback(
    async (isRefresh) => {
      isRefresh ? setIsRefreshing(true) : setIsLoading(true);
      setError(null);

      try {
        const fetchedContacts = await fetchContacts();
        const withUnreadFlag = await Promise.all(
          fetchedContacts.map(async (contact) => {
            const conversation = await fetchMessages(contact.id).catch(() => []);
            const hasUnread = conversation.some((message) => message.senderId === contact.id && !message.read);

            return { ...contact, hasUnread };
          }),
        );
        setContacts(withUnreadFlag);
      } catch {
        setError(GENERIC_LOAD_ERROR);
      } finally {
        isRefresh ? setIsRefreshing(false) : setIsLoading(false);
      }
    },
    [],
  );

  useFocusEffect(
    useCallback(() => {
      load(false);
    }, [load]),
  );

  // Cet écran a déjà calculé le statut non lu de chaque contact pour son
  // propre affichage : on le pousse au badge partagé (BottomNav) plutôt que
  // de déclencher un second aller-retour réseau redondant (cf. refresh() du
  // contexte, utilisé par les autres écrans).
  useEffect(() => {
    setUnreadMessagesCount(contacts.filter((contact) => contact.hasUnread).length);
  }, [contacts, setUnreadMessagesCount]);

  if (isLoading) {
    return (
      <View style={[styles.screen, styles.centered]}>
        <ActivityIndicator color={COLORS.primary} size="large" />
      </View>
    );
  }

  return (
    <View style={styles.screen}>
      <View style={styles.topChrome}>
        <Header displayName={displayName} />
        <SecurityBanner />
      </View>

      {error && (
        <Text style={styles.error} accessibilityRole="alert">
          {error}
        </Text>
      )}

      <ScrollView
        style={styles.list}
        contentContainerStyle={styles.listContent}
        refreshControl={
          <RefreshControl refreshing={isRefreshing} onRefresh={() => load(true)} tintColor={COLORS.primary} />
        }
      >
        <Text style={styles.title}>Messages</Text>

        {contacts.length === 0 ? (
          <Text style={styles.emptyText}>Aucun contact disponible.</Text>
        ) : (
          contacts.map((contact) => (
            <ContactCard
              key={contact.id}
              contact={contact}
              onPress={() =>
                navigation.navigate('Conversation', {
                  contactId: contact.id,
                  firstName: contact.firstName,
                  lastName: contact.lastName,
                  role: contact.role,
                })
              }
            />
          ))
        )}
      </ScrollView>

      <BottomNav
        navigation={navigation}
        activeKey="Messages"
        roles={roles}
        onProfilePress={() => openProfileMenu(navigation, logout, roles)}
      />
    </View>
  );
}

function ContactCard({ contact, onPress }) {
  const name = `${contact.firstName} ${contact.lastName}`;
  const via = viaPatientsLabel(contact.viaPatients);
  const accessibilityLabel = [name, ROLE_LABELS[contact.role], via, contact.hasUnread ? 'message non lu' : null]
    .filter(Boolean)
    .join(', ');

  return (
    <TouchableOpacity
      style={styles.card}
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel}
    >
      <View style={styles.avatar} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
        <Text style={styles.avatarText}>{initials(contact.firstName, contact.lastName)}</Text>
      </View>

      <View style={styles.cardInfo}>
        <Text style={styles.cardName}>{name}</Text>
        <Text style={styles.cardMeta}>{ROLE_LABELS[contact.role]}</Text>
        {via && <Text style={styles.cardVia}>{via}</Text>}
      </View>

      {contact.hasUnread && (
        <View style={styles.unreadBadge} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
          <Text style={styles.unreadBadgeText}>Non lu</Text>
        </View>
      )}
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.background },
  centered: { justifyContent: 'center', alignItems: 'center' },
  topChrome: { backgroundColor: COLORS.surface },
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
  title: { fontSize: TYPE.xl, fontWeight: '700', color: COLORS.primary, marginBottom: 16 },
  emptyText: { color: COLORS.mutedText, fontSize: TYPE.sm },
  card: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    backgroundColor: COLORS.surface,
    borderRadius: 16,
    padding: 16,
    marginBottom: 8,
    minHeight: MIN_TOUCH_TARGET,
  },
  avatar: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: COLORS.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: { color: COLORS.onPrimary, fontWeight: '700' },
  cardInfo: { flex: 1 },
  cardName: { fontSize: TYPE.sm, fontWeight: '700', color: COLORS.primary },
  cardMeta: { fontSize: TYPE.xs, color: COLORS.mutedText, marginTop: 2 },
  cardVia: { fontSize: TYPE.xs, color: COLORS.mutedText, fontStyle: 'italic', marginTop: 2 },
  unreadBadge: {
    borderRadius: 33,
    paddingVertical: 6,
    paddingHorizontal: 12,
    backgroundColor: COLORS.orange.bg,
  },
  unreadBadgeText: { color: COLORS.orange.text, fontWeight: '700', fontSize: TYPE.xs },
});
