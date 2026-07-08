import { useCallback, useMemo, useState } from 'react';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  RefreshControl,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { useAuth } from '../contexts/AuthContext';
import { fetchJournalEntries } from '../services/journalEntryService';
import { fetchPatients } from '../services/patientService';
import { fetchTreatments, scheduleLabel, toggleTreatmentIntake } from '../services/treatmentService';
import { COLORS, TYPE, bloodPressureBand, moodBand, painBand } from '../services/journalPresentation';
import { ROLE_AIDANT, ROLE_LABELS, ROLE_PATIENT, getPrimaryRole } from '../services/roles';

const MIN_TOUCH_TARGET = 44;
const MOOD_SCALE = [1, 2, 3, 4, 5];

const COMING_SOON_TITLE = 'Bientôt disponible';
const COMING_SOON_MESSAGE = 'Cette fonctionnalité arrive dans une prochaine version de MedLink.';

function notifyComingSoon() {
  Alert.alert(COMING_SOON_TITLE, COMING_SOON_MESSAGE);
}

function confirmLogout(logout) {
  Alert.alert('Profil', 'Voulez-vous vous déconnecter ?', [
    { text: 'Annuler', style: 'cancel' },
    { text: 'Se déconnecter', style: 'destructive', onPress: logout },
  ]);
}

export default function JournalScreen() {
  const navigation = useNavigation();
  const { roles, firstName, logout } = useAuth();
  const canCreateEntry = roles.includes(ROLE_PATIENT) || roles.includes(ROLE_AIDANT);
  const primaryRole = getPrimaryRole(roles);
  const displayName = firstName ?? (primaryRole ? ROLE_LABELS[primaryRole] : 'Utilisateur');

  const [entries, setEntries] = useState([]);
  const [treatments, setTreatments] = useState([]);
  const [patientNamesById, setPatientNamesById] = useState({});
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [error, setError] = useState(null);

  const load = useCallback(async (isRefresh) => {
    isRefresh ? setIsRefreshing(true) : setIsLoading(true);
    setError(null);

    try {
      const [fetchedEntries, patients, fetchedTreatments] = await Promise.all([
        fetchJournalEntries(),
        fetchPatients(),
        fetchTreatments(),
      ]);
      setEntries(fetchedEntries);
      setTreatments(fetchedTreatments);
      setPatientNamesById(
        Object.fromEntries(patients.map((patient) => [patient.id, `${patient.firstName} ${patient.lastName}`])),
      );
    } catch {
      setError("Impossible de charger le journal de suivi. Vérifiez votre connexion.");
    } finally {
      isRefresh ? setIsRefreshing(false) : setIsLoading(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      load(false);
    }, [load]),
  );

  // An aidant's feed mixes entries (and treatments) from several patients;
  // showing whose it is only makes sense once there's more than one to tell
  // apart.
  const showPatientName = useMemo(
    () => new Set([...entries, ...treatments].map((item) => item.patientId)).size > 1,
    [entries, treatments],
  );

  const applyScheduleIntake = useCallback((treatmentId, scheduleId, todayIntake) => {
    setTreatments((current) =>
      current.map((treatment) =>
        treatment.id !== treatmentId
          ? treatment
          : {
              ...treatment,
              schedules: treatment.schedules.map((schedule) =>
                schedule.id === scheduleId ? { ...schedule, todayIntake } : schedule,
              ),
            },
      ),
    );
  }, []);

  const handleToggleIntake = useCallback(
    (treatment, schedule) => {
      const previousIntake = schedule.todayIntake;
      const optimisticIntake = {
        ...previousIntake,
        taken: !previousIntake.taken,
        takenAt: previousIntake.taken ? null : new Date().toISOString(),
      };

      applyScheduleIntake(treatment.id, schedule.id, optimisticIntake);

      toggleTreatmentIntake(previousIntake.id)
        .then((updatedIntake) => {
          applyScheduleIntake(treatment.id, schedule.id, updatedIntake);
        })
        .catch(() => {
          applyScheduleIntake(treatment.id, schedule.id, previousIntake);
          setError('Impossible de mettre à jour ce traitement. Réessayez.');
        });
    },
    [applyScheduleIntake],
  );

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

      {canCreateEntry && (
        <TouchableOpacity
          style={styles.addButton}
          onPress={() => navigation.navigate('NewEntry')}
          accessibilityRole="button"
          accessibilityLabel="Ajouter une entrée"
        >
          <Text style={styles.addButtonText}>+ Ajouter une entrée</Text>
        </TouchableOpacity>
      )}

      {error && (
        <Text style={styles.error} accessibilityRole="alert">
          {error}
        </Text>
      )}

      <FlatList
        style={styles.list}
        data={entries}
        keyExtractor={(entry) => String(entry.id)}
        contentContainerStyle={[styles.listContent, entries.length === 0 && styles.emptyList]}
        refreshControl={
          <RefreshControl refreshing={isRefreshing} onRefresh={() => load(true)} tintColor={COLORS.primary} />
        }
        ListEmptyComponent={
          !error && <Text style={styles.emptyText}>Aucune entrée pour le moment.</Text>
        }
        renderItem={({ item, index }) => (
          <JournalEntryCard
            entry={item}
            eyebrow={entryEyebrow(item, index)}
            patientName={showPatientName ? patientNamesById[item.patientId] : null}
          />
        )}
        ListFooterComponent={
          <TreatmentsSection
            treatments={treatments}
            patientNamesById={patientNamesById}
            showPatientName={showPatientName}
            onToggle={handleToggleIntake}
          />
        }
      />

      <BottomNav navigation={navigation} onProfilePress={() => confirmLogout(logout)} />
    </View>
  );
}

function entryEyebrow(entry, index) {
  if (index === 0) return isToday(entry.createdAt) ? 'Entrée du jour' : 'Dernière entrée';
  if (index === 1) return 'Entrée précédente';

  return null;
}

function isToday(isoDate) {
  const date = new Date(isoDate);
  const now = new Date();

  return (
    date.getFullYear() === now.getFullYear() &&
    date.getMonth() === now.getMonth() &&
    date.getDate() === now.getDate()
  );
}

function Header({ displayName }) {
  return (
    <View style={styles.header}>
      <View style={styles.headerBrand}>
        <Text style={styles.headerLogo} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
          🛡️
        </Text>
        <View>
          <Text style={styles.headerTitle}>MedLink</Text>
          <Text style={styles.headerPatientName}>{displayName}</Text>
        </View>
      </View>

      <View accessible accessibilityRole="text" accessibilityLabel="Connexion sécurisée">
        <Text style={styles.headerLock} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
          🔒
        </Text>
      </View>
    </View>
  );
}

function SecurityBanner() {
  return (
    <View style={styles.securityBanner}>
      <Text style={styles.securityBannerText}>Données chiffrées · accès soignants uniquement</Text>
    </View>
  );
}

const BOTTOM_NAV_ITEMS = [
  { key: 'Journal', icon: '📓', screen: 'Journal' },
  { key: 'Messages', icon: '💬', screen: null },
  { key: 'RDV', icon: '📅', screen: null },
  { key: 'Export', icon: '📤', screen: null },
  { key: 'Profil', icon: '👤', screen: null },
];

function BottomNav({ navigation, onProfilePress }) {
  return (
    <View style={styles.bottomNav}>
      {BOTTOM_NAV_ITEMS.map((item) => {
        const isActive = item.key === 'Journal';

        const onPress = () => {
          if (item.key === 'Profil') return onProfilePress();
          if (item.screen) return navigation.navigate(item.screen);

          return notifyComingSoon();
        };

        return (
          <TouchableOpacity
            key={item.key}
            style={styles.bottomNavItem}
            onPress={onPress}
            accessibilityRole="button"
            accessibilityState={{ selected: isActive }}
            accessibilityLabel={item.key}
          >
            <Text
              style={styles.bottomNavIcon}
              accessibilityElementsHidden
              importantForAccessibility="no-hide-descendants"
            >
              {item.icon}
            </Text>
            <Text style={[styles.bottomNavLabel, isActive && styles.bottomNavLabelActive]}>{item.key}</Text>
          </TouchableOpacity>
        );
      })}
    </View>
  );
}

function JournalEntryCard({ entry, eyebrow, patientName }) {
  const mood = moodBand(entry.mood);
  const pain = painBand(entry.painLevel);
  const bloodPressure = bloodPressureBand(entry.bloodPressure);
  const date = new Date(entry.createdAt).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });

  return (
    <View>
      {eyebrow && <Text style={styles.eyebrow}>{eyebrow.toUpperCase()}</Text>}

      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Text style={styles.date}>{date}</Text>
          {entry.enteredByCaregiver && (
            <Text style={styles.caregiverTag} accessibilityLabel="Saisie par l'aidant">
              Saisie par l'aidant
            </Text>
          )}
        </View>

        {patientName && <Text style={styles.patientName}>{patientName}</Text>}

        <MetricRow
          label="Humeur"
          visual={<MoodDots mood={entry.mood} color={mood.text} />}
          band={mood}
          accessibilityLabel={`Humeur : ${mood.label}`}
        />
        <MetricRow
          label="Douleur"
          visual={<Text style={styles.metricNumber}>{entry.painLevel}/10</Text>}
          band={pain}
          accessibilityLabel={`Douleur : ${entry.painLevel} sur 10, ${pain.label}`}
        />
        <MetricRow
          label="Tension"
          visual={<Text style={styles.metricNumber}>{entry.bloodPressure}</Text>}
          band={bloodPressure}
          accessibilityLabel={`Tension : ${entry.bloodPressure}, ${bloodPressure.label}`}
        />

        {entry.note && <Text style={styles.note}>{entry.note}</Text>}
      </View>
    </View>
  );
}

function MetricRow({ label, visual, band, accessibilityLabel }) {
  return (
    <View style={styles.metricRow}>
      <Text style={styles.metricLabel}>{label}</Text>
      <View style={styles.metricValue}>{visual}</View>
      <View
        style={[styles.badge, { backgroundColor: band.bg }]}
        accessible
        accessibilityRole="text"
        accessibilityLabel={accessibilityLabel}
      >
        <Text style={[styles.badgeText, { color: band.text }]}>{band.label}</Text>
      </View>
    </View>
  );
}

function MoodDots({ mood, color }) {
  return (
    <View style={styles.dotsRow} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
      {MOOD_SCALE.map((value) => (
        <View key={value} style={[styles.dot, value === mood && { backgroundColor: color }]} />
      ))}
    </View>
  );
}

function formatTime(isoDate) {
  return new Date(isoDate).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
}

function TreatmentsSection({ treatments, patientNamesById, showPatientName, onToggle }) {
  return (
    <View style={styles.treatmentsSection}>
      <Text style={styles.sectionHeading}>Traitements du jour</Text>

      {treatments.length === 0 ? (
        <Text style={styles.emptyText}>Aucun traitement en cours.</Text>
      ) : (
        treatments.map((treatment) => (
          <TreatmentCard
            key={treatment.id}
            treatment={treatment}
            patientName={showPatientName ? patientNamesById[treatment.patientId] : null}
            onToggle={onToggle}
          />
        ))
      )}
    </View>
  );
}

function TreatmentCard({ treatment, patientName, onToggle }) {
  const allTaken = treatment.schedules.every((schedule) => schedule.todayIntake?.taken);

  return (
    <View style={styles.treatmentsCard}>
      <View
        style={styles.treatmentCardHeader}
        accessible
        accessibilityRole="text"
        accessibilityLabel={allTaken ? 'Tous les horaires du jour sont pris' : 'Certains horaires du jour restent à prendre'}
      >
        <Text style={styles.treatmentIcon} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
          {allTaken ? '✅' : '⭕'}
        </Text>

        <View style={styles.treatmentInfo}>
          {patientName && <Text style={styles.patientName}>{patientName}</Text>}
          <Text style={styles.treatmentName}>
            {treatment.name} · {treatment.dosage}
          </Text>
        </View>
      </View>

      {treatment.schedules.map((schedule, index) => (
        <TreatmentScheduleRow
          key={schedule.id}
          treatment={treatment}
          schedule={schedule}
          isLast={index === treatment.schedules.length - 1}
          onToggle={onToggle}
        />
      ))}
    </View>
  );
}

function TreatmentScheduleRow({ treatment, schedule, isLast, onToggle }) {
  const { taken, takenAt } = schedule.todayIntake ?? { taken: false, takenAt: null };
  const color = taken ? COLORS.green.text : COLORS.mutedText;
  const accessibilityLabel = taken
    ? `${treatment.name} ${treatment.dosage}, pris à ${formatTime(takenAt)}`
    : `${treatment.name} ${treatment.dosage}, à prendre : ${scheduleLabel(schedule)} — appuyer pour marquer comme pris`;

  return (
    <TouchableOpacity
      style={[styles.treatmentRow, isLast && styles.treatmentRowLast]}
      onPress={() => onToggle(treatment, schedule)}
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel}
    >
      <Text style={styles.treatmentIcon} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
        {taken ? '✅' : '⭕'}
      </Text>

      <Text style={[styles.treatmentStatus, { color }]}>
        {taken ? `Pris à ${formatTime(takenAt)}` : `À prendre : ${scheduleLabel(schedule)}`}
      </Text>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.background },
  centered: { justifyContent: 'center', alignItems: 'center' },
  topChrome: { backgroundColor: COLORS.surface },
  header: {
    backgroundColor: COLORS.primary,
    paddingTop: 56,
    paddingBottom: 16,
    paddingHorizontal: 20,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  headerBrand: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  headerLogo: { fontSize: TYPE.lg },
  headerTitle: { color: COLORS.onPrimary, fontSize: TYPE.md, fontWeight: '700' },
  headerPatientName: { color: COLORS.onPrimary, fontSize: TYPE.sm, opacity: 0.85 },
  headerLock: { fontSize: TYPE.lg },
  securityBanner: {
    backgroundColor: COLORS.mutedBackground,
    paddingVertical: 10,
    paddingHorizontal: 20,
  },
  securityBannerText: { color: COLORS.primary, fontSize: TYPE.xs, fontWeight: '600' },
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
  eyebrow: {
    color: COLORS.primary,
    fontSize: TYPE.xs,
    fontWeight: '700',
    letterSpacing: 0.5,
    marginBottom: 8,
    marginTop: 4,
  },
  card: {
    backgroundColor: COLORS.surface,
    borderRadius: 16,
    padding: 16,
    marginBottom: 16,
  },
  cardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  date: { fontSize: TYPE.xs, color: COLORS.mutedText },
  caregiverTag: { fontSize: TYPE.xs, fontWeight: '600', color: COLORS.primary },
  patientName: { fontSize: TYPE.sm, fontWeight: '700', color: COLORS.primary, marginBottom: 8 },
  metricRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 6,
  },
  metricLabel: { fontSize: TYPE.sm, color: COLORS.mutedText, width: 70 },
  metricValue: { flex: 1, alignItems: 'flex-start', paddingHorizontal: 8 },
  metricNumber: { fontSize: TYPE.sm, fontWeight: '700', color: COLORS.primary },
  dotsRow: { flexDirection: 'row', gap: 6 },
  dot: { width: 10, height: 10, borderRadius: 5, backgroundColor: COLORS.border },
  badge: { borderRadius: 33, paddingVertical: 6, paddingHorizontal: 12 },
  badgeText: { fontSize: TYPE.xs, fontWeight: '700' },
  note: {
    fontSize: TYPE.sm,
    color: COLORS.primary,
    backgroundColor: COLORS.mutedBackground,
    borderRadius: 12,
    padding: 12,
    marginTop: 8,
  },
  addButton: {
    minHeight: 48,
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 2,
    borderStyle: 'dashed',
    borderColor: COLORS.border,
    borderRadius: 33,
    backgroundColor: COLORS.surface,
    marginHorizontal: 20,
    marginTop: 16,
  },
  addButtonText: { color: COLORS.primary, fontWeight: '700', fontSize: TYPE.sm },
  bottomNav: {
    flexDirection: 'row',
    backgroundColor: COLORS.primary,
    paddingTop: 8,
    paddingBottom: 20,
  },
  bottomNavItem: {
    flex: 1,
    minHeight: MIN_TOUCH_TARGET,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 2,
  },
  bottomNavIcon: { fontSize: TYPE.md },
  bottomNavLabel: { fontSize: TYPE.xs, color: 'rgba(255,255,255,0.7)' },
  bottomNavLabelActive: { color: COLORS.onPrimary, fontWeight: '700' },
  treatmentsSection: { marginTop: 8 },
  sectionHeading: {
    color: COLORS.primary,
    fontSize: TYPE.md,
    fontWeight: '700',
    marginBottom: 8,
  },
  treatmentsCard: {
    backgroundColor: COLORS.surface,
    borderRadius: 16,
    overflow: 'hidden',
    marginBottom: 12,
  },
  treatmentCardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    minHeight: MIN_TOUCH_TARGET,
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  treatmentRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    minHeight: MIN_TOUCH_TARGET,
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  treatmentRowLast: {
    borderBottomWidth: 0,
  },
  treatmentIcon: { fontSize: TYPE.lg },
  treatmentInfo: { flex: 1 },
  treatmentName: { fontSize: TYPE.sm, fontWeight: '700', color: COLORS.primary },
  treatmentStatus: { fontSize: TYPE.xs, fontWeight: '600', marginTop: 2 },
});
