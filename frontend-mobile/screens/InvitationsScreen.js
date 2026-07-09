import { useCallback, useState } from 'react';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import {
  ActivityIndicator,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import BottomNav, { openProfileMenu } from '../components/BottomNav';
import Header from '../components/Header';
import SecurityBanner from '../components/SecurityBanner';
import { useAuth } from '../contexts/AuthContext';
import { useInvitationsBadge } from '../contexts/InvitationsBadgeContext';
import { acceptInvitation, fetchReceivedInvitations, rejectInvitation } from '../services/liaisonService';
import { COLORS, TYPE } from '../services/journalPresentation';
import { ROLE_LABELS, getPrimaryRole } from '../services/roles';

const MIN_TOUCH_TARGET = 44;
const GENERIC_LOAD_ERROR = 'Impossible de charger vos invitations. Vérifiez votre connexion.';
const GENERIC_RESPOND_ERROR = 'Impossible de traiter cette invitation, réessayez.';

function initials(firstName, lastName) {
  return `${firstName[0] ?? ''}${lastName[0] ?? ''}`.toUpperCase();
}

export default function InvitationsScreen() {
  const navigation = useNavigation();
  const { firstName, roles, logout } = useAuth();
  const primaryRole = getPrimaryRole(roles);
  const displayName = firstName ?? (primaryRole ? ROLE_LABELS[primaryRole] : 'Utilisateur');

  const [invitations, setInvitations] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [error, setError] = useState(null);
  const [respondingId, setRespondingId] = useState(null);
  const { decrement: decrementPendingInvitationsCount } = useInvitationsBadge();

  const load = useCallback(async (isRefresh) => {
    isRefresh ? setIsRefreshing(true) : setIsLoading(true);
    setError(null);

    try {
      setInvitations(await fetchReceivedInvitations());
    } catch {
      setError(GENERIC_LOAD_ERROR);
    } finally {
      isRefresh ? setIsRefreshing(false) : setIsLoading(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      load(false);
    }, [load]),
  );

  const respond = useCallback(async (invitation, action) => {
    setError(null);
    setRespondingId(invitation.id);

    try {
      await action(invitation.id);
      setInvitations((current) => current.filter((item) => item.id !== invitation.id));
      decrementPendingInvitationsCount();
    } catch {
      setError(GENERIC_RESPOND_ERROR);
    } finally {
      setRespondingId(null);
    }
  }, [decrementPendingInvitationsCount]);

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
        <Text style={styles.title}>Invitations en attente</Text>
        <Text
          style={styles.countAnnouncement}
          accessible
          accessibilityRole="text"
          accessibilityLabel={`${invitations.length} invitation${invitations.length > 1 ? 's' : ''} en attente`}
        >
          {invitations.length} invitation{invitations.length > 1 ? 's' : ''} en attente
        </Text>

        {invitations.length === 0 ? (
          <Text style={styles.emptyText}>Aucune invitation en attente.</Text>
        ) : (
          invitations.map((invitation) => (
            <InvitationCard
              key={invitation.id}
              invitation={invitation}
              isResponding={respondingId === invitation.id}
              onAccept={() => respond(invitation, acceptInvitation)}
              onReject={() => respond(invitation, rejectInvitation)}
            />
          ))
        )}
      </ScrollView>

      <BottomNav
        navigation={navigation}
        activeKey={null}
        roles={roles}
        onProfilePress={() => openProfileMenu(navigation, logout, roles)}
      />
    </View>
  );
}

function InvitationCard({ invitation, isResponding, onAccept, onReject }) {
  const name = `${invitation.patientFirstName} ${invitation.patientLastName}`;
  const roleLabel = ROLE_LABELS[invitation.inviteeRole];

  return (
    <View style={styles.card}>
      <View style={styles.avatar} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
        <Text style={styles.avatarText}>{initials(invitation.patientFirstName, invitation.patientLastName)}</Text>
      </View>

      <View style={styles.cardInfo}>
        <Text style={styles.cardName}>{name}</Text>
        <Text style={styles.cardMeta}>souhaite vous ajouter comme {roleLabel}</Text>
      </View>

      <View style={styles.cardActions}>
        <TouchableOpacity
          style={styles.acceptButton}
          onPress={onAccept}
          disabled={isResponding}
          accessibilityRole="button"
          accessibilityLabel={`Accepter l'invitation de ${name}`}
        >
          <Text style={styles.acceptButtonText}>Accepter</Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={styles.rejectButton}
          onPress={onReject}
          disabled={isResponding}
          accessibilityRole="button"
          accessibilityLabel={`Refuser l'invitation de ${name}`}
        >
          <Text style={styles.rejectButtonText}>Refuser</Text>
        </TouchableOpacity>
      </View>
    </View>
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
  title: { fontSize: TYPE.xl, fontWeight: '700', color: COLORS.primary, marginBottom: 4 },
  countAnnouncement: { fontSize: TYPE.sm, color: COLORS.mutedText, marginBottom: 16 },
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
  cardActions: { gap: 8 },
  acceptButton: {
    minHeight: MIN_TOUCH_TARGET,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 16,
    borderRadius: 33,
    backgroundColor: COLORS.primary,
  },
  acceptButtonText: { color: COLORS.onPrimary, fontWeight: '700', fontSize: TYPE.xs },
  rejectButton: {
    minHeight: MIN_TOUCH_TARGET,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 16,
    borderRadius: 33,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  rejectButtonText: { color: COLORS.mutedText, fontWeight: '700', fontSize: TYPE.xs },
});
