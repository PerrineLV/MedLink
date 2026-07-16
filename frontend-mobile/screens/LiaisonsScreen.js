import { useCallback, useState } from 'react';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import {
  ActivityIndicator,
  Alert,
  RefreshControl,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { KeyboardAwareScrollView } from 'react-native-keyboard-aware-scroll-view';
import BottomNav from '../components/BottomNav';
import Header from '../components/Header';
import SecurityBanner from '../components/SecurityBanner';
import { useAuth } from '../contexts/AuthContext';
import { fetchLiaisons, inviteLiaison, revokeLiaison } from '../services/liaisonService';
import { COLORS, TYPE } from '../services/journalPresentation';
import { ROLE_LABELS } from '../services/roles';

const MIN_TOUCH_TARGET = 44;
const GENERIC_LOAD_ERROR = 'Impossible de charger vos liaisons. Vérifiez votre connexion.';
const GENERIC_INVITE_ERROR = "Impossible d'envoyer l'invitation, réessayez.";
const GENERIC_REVOKE_ERROR = 'Impossible de révoquer ce lien, réessayez.';

function initials(firstName, lastName) {
  return `${firstName[0] ?? ''}${lastName[0] ?? ''}`.toUpperCase();
}

function confirmRevoke(liaison, onConfirm) {
  const name = `${liaison.inviteeFirstName} ${liaison.inviteeLastName}`;

  Alert.alert(
    'Révoquer cet accès ?',
    `Révoquer l'accès de ${name} ? Il/elle ne pourra plus consulter votre suivi.`,
    [
      { text: 'Annuler', style: 'cancel' },
      { text: 'Confirmer', style: 'destructive', onPress: onConfirm },
    ],
  );
}

export default function LiaisonsScreen() {
  const navigation = useNavigation();
  const { firstName, roles, logout } = useAuth();
  const displayName = firstName ?? ROLE_LABELS.ROLE_PATIENT;

  const [liaisons, setLiaisons] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [error, setError] = useState(null);

  const load = useCallback(async (isRefresh) => {
    isRefresh ? setIsRefreshing(true) : setIsLoading(true);
    setError(null);

    try {
      setLiaisons(await fetchLiaisons());
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

  const handleInvited = useCallback((liaison) => {
    setLiaisons((current) => [liaison, ...current]);
  }, []);

  const handleRevoke = useCallback((liaison) => {
    confirmRevoke(liaison, () => {
      revokeLiaison(liaison.id)
        .then(() => {
          setLiaisons((current) => current.filter((item) => item.id !== liaison.id));
        })
        .catch(() => {
          setError(GENERIC_REVOKE_ERROR);
        });
    });
  }, []);

  const activeLiaisons = liaisons.filter((liaison) => liaison.active);
  const pendingLiaisons = liaisons.filter((liaison) => !liaison.active);

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

      <KeyboardAwareScrollView
        style={styles.list}
        contentContainerStyle={styles.listContent}
        enableOnAndroid
        refreshControl={
          <RefreshControl
            refreshing={isRefreshing}
            onRefresh={() => load(true)}
            tintColor={COLORS.primary}
          />
        }
      >
        <Text style={styles.title}>Mes liaisons</Text>

        <InviteForm onInvited={handleInvited} onError={setError} />

        <Text style={styles.sectionHeading}>Actifs</Text>
        {activeLiaisons.length === 0 ? (
          <Text style={styles.emptyText}>Aucun lien actif pour le moment.</Text>
        ) : (
          activeLiaisons.map((liaison) => (
            <LiaisonCard key={liaison.id} liaison={liaison} onRevoke={handleRevoke} />
          ))
        )}

        <Text style={styles.sectionHeading}>En attente</Text>
        {pendingLiaisons.length === 0 ? (
          <Text style={styles.emptyText}>Aucune invitation en attente.</Text>
        ) : (
          pendingLiaisons.map((liaison) => <LiaisonCard key={liaison.id} liaison={liaison} />)
        )}
      </KeyboardAwareScrollView>

      <BottomNav navigation={navigation} activeKey={null} roles={roles} logout={logout} />
    </View>
  );
}

function LiaisonCard({ liaison, onRevoke }) {
  const name = `${liaison.inviteeFirstName} ${liaison.inviteeLastName}`;

  return (
    <View style={styles.card}>
      <View
        style={styles.avatar}
        accessibilityElementsHidden
        importantForAccessibility="no-hide-descendants"
      >
        <Text style={styles.avatarText}>
          {initials(liaison.inviteeFirstName, liaison.inviteeLastName)}
        </Text>
      </View>

      <View style={styles.cardInfo}>
        <Text style={styles.cardName}>{name}</Text>
        <Text style={styles.cardMeta}>{ROLE_LABELS[liaison.inviteeRole]}</Text>
      </View>

      {onRevoke ? (
        <TouchableOpacity
          style={styles.revokeButton}
          onPress={() => onRevoke(liaison)}
          accessibilityRole="button"
          accessibilityLabel={`Révoquer l'accès de ${name}`}
        >
          <Text style={styles.revokeButtonText}>Révoquer</Text>
        </TouchableOpacity>
      ) : (
        <View
          style={styles.pendingBadge}
          accessible
          accessibilityRole="text"
          accessibilityLabel={`${name}, invitation en attente`}
        >
          <Text style={styles.pendingBadgeText}>En attente</Text>
        </View>
      )}
    </View>
  );
}

function InviteForm({ onInvited, onError }) {
  const [email, setEmail] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async () => {
    onError(null);
    setIsSubmitting(true);

    try {
      const liaison = await inviteLiaison(email.trim());
      onInvited(liaison);
      setEmail('');
    } catch (requestError) {
      onError(requestError.response?.data?.detail ?? GENERIC_INVITE_ERROR);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <View style={styles.inviteForm}>
      <Text style={styles.inviteLabel}>Inviter un aidant ou un soignant</Text>
      <View style={styles.inviteRow}>
        <TextInput
          style={styles.inviteInput}
          value={email}
          onChangeText={setEmail}
          placeholder="email@exemple.fr"
          keyboardType="email-address"
          autoCapitalize="none"
          accessibilityLabel="Adresse e-mail à inviter"
        />
        <TouchableOpacity
          style={styles.inviteButton}
          onPress={handleSubmit}
          disabled={isSubmitting || email.trim().length === 0}
          accessibilityRole="button"
          accessibilityLabel="Inviter"
        >
          <Text style={styles.inviteButtonText}>{isSubmitting ? 'Envoi…' : 'Inviter'}</Text>
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
  title: { fontSize: TYPE.xl, fontWeight: '700', color: COLORS.primary, marginBottom: 16 },
  sectionHeading: {
    color: COLORS.primary,
    fontSize: TYPE.md,
    fontWeight: '700',
    marginTop: 16,
    marginBottom: 8,
  },
  emptyText: { color: COLORS.mutedText, fontSize: TYPE.sm },
  inviteForm: {
    backgroundColor: COLORS.surface,
    borderRadius: 16,
    padding: 16,
  },
  inviteLabel: { fontSize: TYPE.sm, fontWeight: '700', color: COLORS.primary, marginBottom: 8 },
  inviteRow: { flexDirection: 'row', gap: 8 },
  inviteInput: {
    flex: 1,
    minHeight: MIN_TOUCH_TARGET,
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 33,
    paddingHorizontal: 16,
    fontSize: TYPE.sm,
  },
  inviteButton: {
    minHeight: MIN_TOUCH_TARGET,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 20,
    borderRadius: 33,
    backgroundColor: COLORS.primary,
  },
  inviteButtonText: { color: COLORS.onPrimary, fontWeight: '700', fontSize: TYPE.sm },
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
  revokeButton: {
    minHeight: MIN_TOUCH_TARGET,
    justifyContent: 'center',
    paddingHorizontal: 16,
    borderRadius: 33,
    borderWidth: 1,
    borderColor: COLORS.red.text,
  },
  revokeButtonText: { color: COLORS.red.text, fontWeight: '700', fontSize: TYPE.xs },
  pendingBadge: {
    borderRadius: 33,
    paddingVertical: 8,
    paddingHorizontal: 12,
    backgroundColor: COLORS.orange.bg,
  },
  pendingBadgeText: { color: COLORS.orange.text, fontWeight: '700', fontSize: TYPE.xs },
});
