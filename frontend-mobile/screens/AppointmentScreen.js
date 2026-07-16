import { useCallback, useMemo, useState } from 'react';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import {
  ActivityIndicator,
  Alert,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import BottomNav from '../components/BottomNav';
import Header from '../components/Header';
import SecurityBanner from '../components/SecurityBanner';
import { useAuth } from '../contexts/AuthContext';
import {
  APPOINTMENT_STATUS,
  cancelAppointment,
  fetchAppointments,
  isUpcoming,
  isWithin24Hours,
} from '../services/appointmentService';
import { fetchContacts } from '../services/messageService';
import { fetchPatients } from '../services/patientService';
import { COLORS, TYPE } from '../services/journalPresentation';
import { ROLE_AIDANT, ROLE_LABELS, ROLE_SOIGNANT, getPrimaryRole } from '../services/roles';

const MIN_TOUCH_TARGET = 44;
const GENERIC_LOAD_ERROR = 'Impossible de charger vos rendez-vous. Vérifiez votre connexion.';
const GENERIC_CANCEL_ERROR = "Impossible d'annuler ce rendez-vous. Réessayez.";

function contactDisplayName(contact) {
  return contact ? `${contact.firstName} ${contact.lastName}` : 'Soignant';
}

export default function AppointmentScreen() {
  const navigation = useNavigation();
  const { firstName, roles, logout } = useAuth();
  const isSoignant = roles.includes(ROLE_SOIGNANT);
  const isAidant = roles.includes(ROLE_AIDANT);
  const primaryRole = getPrimaryRole(roles);
  const displayName = firstName ?? (primaryRole ? ROLE_LABELS[primaryRole] : 'Utilisateur');

  const [appointments, setAppointments] = useState([]);
  const [patientNamesById, setPatientNamesById] = useState({});
  const [soignantNamesById, setSoignantNamesById] = useState({});
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [error, setError] = useState(null);

  // Un soignant a besoin du nom du patient (il en a plusieurs) ; un
  // patient/aidant a besoin du nom du soignant (calculé via les contacts
  // ML-70, seule source déjà disponible pour ce lien patient <-> soignant).
  // Le nom du patient est aussi chargé dans ce second cas : un aidant suivant
  // plusieurs patients a besoin de savoir chez quel soignant *lequel* de ses
  // patients a rendez-vous (cf. showPatientName plus bas), pas seulement le
  // nom du soignant.
  const load = useCallback(
    async (isRefresh) => {
      isRefresh ? setIsRefreshing(true) : setIsLoading(true);
      setError(null);

      try {
        const [fetchedAppointments, patients, contacts] = await Promise.all([
          fetchAppointments(),
          fetchPatients(),
          isSoignant ? Promise.resolve([]) : fetchContacts(),
        ]);
        setAppointments(fetchedAppointments);
        setPatientNamesById(
          Object.fromEntries(
            patients.map((patient) => [patient.id, `${patient.firstName} ${patient.lastName}`]),
          ),
        );
        setSoignantNamesById(
          Object.fromEntries(
            contacts
              .filter((contact) => contact.role === ROLE_SOIGNANT)
              .map((contact) => [contact.id, contactDisplayName(contact)]),
          ),
        );
      } catch {
        setError(GENERIC_LOAD_ERROR);
      } finally {
        isRefresh ? setIsRefreshing(false) : setIsLoading(false);
      }
    },
    [isSoignant],
  );

  useFocusEffect(
    useCallback(() => {
      load(false);
    }, [load]),
  );

  const { upcoming, past } = useMemo(() => {
    const sorted = [...appointments].sort(
      (a, b) => new Date(a.scheduledAt) - new Date(b.scheduledAt),
    );
    return {
      upcoming: sorted.filter(isUpcoming),
      past: sorted.filter((appointment) => !isUpcoming(appointment)).reverse(),
    };
  }, [appointments]);

  // Contrairement au journal/aux traitements (affiché seulement si ≥ 2
  // patients parmi les entrées visibles), on l'affiche systématiquement pour
  // un aidant : le nom ne doit pas apparaître/disparaître selon que l'un de
  // ses patients a ou non un RDV en ce moment.
  const showPatientName = isAidant;

  const handleCancel = useCallback((appointment) => {
    Alert.alert('Annuler le rendez-vous', 'Voulez-vous vraiment annuler ce rendez-vous ?', [
      { text: 'Non', style: 'cancel' },
      {
        text: 'Oui, annuler',
        style: 'destructive',
        onPress: async () => {
          try {
            const updated = await cancelAppointment(appointment.id);
            setAppointments((current) =>
              current.map((item) => (item.id === updated.id ? updated : item)),
            );
          } catch {
            Alert.alert('Erreur', GENERIC_CANCEL_ERROR);
          }
        },
      },
    ]);
  }, []);

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

      {isSoignant && (
        <TouchableOpacity
          style={styles.addButton}
          onPress={() => navigation.navigate('NewAppointment')}
          accessibilityRole="button"
          accessibilityLabel="Nouveau rendez-vous"
        >
          <Text style={styles.addButtonText}>+ Nouveau RDV</Text>
        </TouchableOpacity>
      )}

      {error && (
        <Text style={styles.error} accessibilityRole="alert">
          {error}
        </Text>
      )}

      <ScrollView
        style={styles.list}
        contentContainerStyle={styles.listContent}
        refreshControl={
          <RefreshControl
            refreshing={isRefreshing}
            onRefresh={() => load(true)}
            tintColor={COLORS.primary}
          />
        }
      >
        <Text style={styles.sectionHeading}>À venir</Text>
        {upcoming.length === 0 ? (
          <Text style={styles.emptyText}>Aucun rendez-vous à venir.</Text>
        ) : (
          upcoming.map((appointment) => (
            <AppointmentCard
              key={appointment.id}
              appointment={appointment}
              name={
                isSoignant
                  ? (patientNamesById[appointment.patientId] ?? 'Patient')
                  : (soignantNamesById[appointment.soignantId] ?? 'Soignant')
              }
              patientName={showPatientName ? patientNamesById[appointment.patientId] : null}
              isSoignant={isSoignant}
              onCancel={handleCancel}
            />
          ))
        )}

        <Text style={[styles.sectionHeading, styles.pastHeading]}>Passés</Text>
        {past.length === 0 ? (
          <Text style={styles.emptyText}>Aucun rendez-vous passé.</Text>
        ) : (
          past.map((appointment) => (
            <AppointmentCard
              key={appointment.id}
              appointment={appointment}
              name={
                isSoignant
                  ? (patientNamesById[appointment.patientId] ?? 'Patient')
                  : (soignantNamesById[appointment.soignantId] ?? 'Soignant')
              }
              patientName={showPatientName ? patientNamesById[appointment.patientId] : null}
              isSoignant={isSoignant}
              isPast
            />
          ))
        )}
      </ScrollView>

      <BottomNav navigation={navigation} activeKey="RDV" roles={roles} logout={logout} />
    </View>
  );
}

function formatDay(isoDate) {
  return new Date(isoDate).toLocaleDateString('fr-FR', { day: 'numeric' });
}

function formatMonth(isoDate) {
  return new Date(isoDate).toLocaleDateString('fr-FR', { month: 'short' }).replace('.', '');
}

function formatFullDate(isoDate) {
  return new Date(isoDate).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' });
}

function formatTime(isoDate) {
  return new Date(isoDate).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
}

function AppointmentCard({
  appointment,
  name,
  patientName = null,
  isSoignant,
  isPast = false,
  onCancel,
}) {
  const showReminder =
    !isPast && appointment.status === APPOINTMENT_STATUS.PLANNED && isWithin24Hours(appointment);
  const isCancelled = appointment.status === APPOINTMENT_STATUS.CANCELLED;
  const canCancel = isSoignant && !isPast && appointment.status === APPOINTMENT_STATUS.PLANNED;

  const accessibilityLabel = [
    patientName ? `Patient ${patientName}` : null,
    `${formatFullDate(appointment.scheduledAt)}, ${name}, ${formatTime(appointment.scheduledAt)}`,
    appointment.notes,
    isCancelled ? 'Rendez-vous annulé' : null,
    showReminder ? 'Rendez-vous dans moins de 24 heures' : null,
  ]
    .filter(Boolean)
    .join(', ');

  return (
    <View
      style={[styles.card, isPast && styles.cardPast]}
      accessible
      accessibilityRole="text"
      accessibilityLabel={accessibilityLabel}
    >
      <View
        style={styles.datePad}
        accessibilityElementsHidden
        importantForAccessibility="no-hide-descendants"
      >
        <Text style={styles.datePadDay}>{formatDay(appointment.scheduledAt)}</Text>
        <Text style={styles.datePadMonth}>{formatMonth(appointment.scheduledAt)}</Text>
      </View>

      <View
        style={styles.cardInfo}
        accessibilityElementsHidden
        importantForAccessibility="no-hide-descendants"
      >
        {patientName && <Text style={styles.patientName}>{patientName}</Text>}
        <View style={styles.cardHeader}>
          <Text style={styles.cardName}>{name}</Text>
          {showReminder && <Text style={styles.reminderIcon}>🔔</Text>}
        </View>
        <Text style={styles.cardMeta}>
          {formatTime(appointment.scheduledAt)}
          {appointment.notes ? ` · ${appointment.notes}` : ''}
        </Text>
        {isCancelled && <Text style={styles.cancelledTag}>Annulé</Text>}
      </View>

      {canCancel && (
        <TouchableOpacity
          style={styles.cancelButton}
          onPress={() => onCancel(appointment)}
          accessibilityRole="button"
          accessibilityLabel={`Annuler le rendez-vous du ${formatFullDate(appointment.scheduledAt)} avec ${name}`}
        >
          <Text style={styles.cancelButtonText}>Annuler</Text>
        </TouchableOpacity>
      )}
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
  addButton: {
    minHeight: 48,
    justifyContent: 'center',
    alignItems: 'center',
    borderRadius: 33,
    backgroundColor: COLORS.primary,
    marginHorizontal: 20,
    marginTop: 16,
  },
  addButtonText: { color: COLORS.onPrimary, fontWeight: '700', fontSize: TYPE.sm },
  list: { flex: 1 },
  listContent: { padding: 20, gap: 8 },
  sectionHeading: {
    color: COLORS.primary,
    fontSize: TYPE.md,
    fontWeight: '700',
    marginBottom: 8,
  },
  pastHeading: { marginTop: 16 },
  emptyText: { color: COLORS.mutedText, fontSize: TYPE.sm, marginBottom: 8 },
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
  cardPast: { opacity: 0.7 },
  datePad: {
    width: 48,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: COLORS.mutedBackground,
    borderRadius: 12,
    paddingVertical: 8,
  },
  datePadDay: { fontSize: TYPE.md, fontWeight: '700', color: COLORS.primary },
  datePadMonth: {
    fontSize: TYPE.xs,
    fontWeight: '700',
    color: COLORS.mutedText,
    textTransform: 'uppercase',
  },
  cardInfo: { flex: 1 },
  patientName: { fontSize: TYPE.xs, fontWeight: '700', color: COLORS.primary, marginBottom: 2 },
  cardHeader: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  cardName: { fontSize: TYPE.sm, fontWeight: '700', color: COLORS.primary },
  reminderIcon: { fontSize: TYPE.sm },
  cardMeta: { fontSize: TYPE.xs, color: COLORS.mutedText, marginTop: 2 },
  cancelledTag: { fontSize: TYPE.xs, fontWeight: '700', color: COLORS.mutedText, marginTop: 4 },
  cancelButton: {
    minHeight: MIN_TOUCH_TARGET,
    minWidth: MIN_TOUCH_TARGET,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 12,
  },
  cancelButtonText: { color: COLORS.red.text, fontWeight: '700', fontSize: TYPE.xs },
});
