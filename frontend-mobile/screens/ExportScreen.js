import { useCallback, useMemo, useState } from 'react';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import {
  ActivityIndicator,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import DateTimePicker, { DateTimePickerAndroid } from '@react-native-community/datetimepicker';
import BottomNav from '../components/BottomNav';
import Header from '../components/Header';
import { useAuth } from '../contexts/AuthContext';
import {
  downloadAndShareJournalPdf,
  extractErrorMessage,
  toISODate,
} from '../services/exportService';
import { fetchJournalEntries } from '../services/journalEntryService';
import { fetchPatients } from '../services/patientService';
import { COLORS, TYPE } from '../services/journalPresentation';
import { ROLE_LABELS, getPrimaryRole } from '../services/roles';

const PERIODS = [
  { key: '7d', label: '7 jours' },
  { key: '30d', label: '30 jours' },
  { key: 'custom', label: 'Personnalisé' },
];

const GENERIC_ERROR = 'Impossible de générer le PDF. Réessayez.';

function startOfDay(date) {
  const copy = new Date(date);
  copy.setHours(0, 0, 0, 0);
  return copy;
}

function addDays(date, days) {
  const copy = new Date(date);
  copy.setDate(copy.getDate() + days);
  return copy;
}

function formatDate(date) {
  return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
}

function periodRange(periodKey, customFrom, customTo) {
  const today = startOfDay(new Date());

  if (periodKey === '7d') return { from: addDays(today, -6), to: today };
  if (periodKey === '30d') return { from: addDays(today, -29), to: today };

  return { from: customFrom, to: customTo };
}

export default function ExportScreen() {
  const navigation = useNavigation();
  const { roles, firstName, logout } = useAuth();
  const primaryRole = getPrimaryRole(roles);
  const displayName = firstName ?? (primaryRole ? ROLE_LABELS[primaryRole] : 'Utilisateur');

  const today = useMemo(() => startOfDay(new Date()), []);

  const [patients, setPatients] = useState(null);
  const [selectedPatientId, setSelectedPatientId] = useState(null);
  const [entries, setEntries] = useState([]);
  const [periodKey, setPeriodKey] = useState('7d');
  const [customFrom, setCustomFrom] = useState(today);
  const [customTo, setCustomTo] = useState(today);
  const [isLoading, setIsLoading] = useState(true);
  const [isGenerating, setIsGenerating] = useState(false);
  const [error, setError] = useState(null);

  useFocusEffect(
    useCallback(() => {
      let cancelled = false;
      setIsLoading(true);
      setError(null);

      Promise.all([fetchPatients(), fetchJournalEntries()])
        .then(([fetchedPatients, fetchedEntries]) => {
          if (cancelled) return;
          setPatients(fetchedPatients);
          setEntries(fetchedEntries);
          setSelectedPatientId((current) => current ?? fetchedPatients[0]?.id ?? null);
        })
        .catch(() => {
          if (!cancelled) setError('Impossible de charger vos données. Vérifiez votre connexion.');
        })
        .finally(() => {
          if (!cancelled) setIsLoading(false);
        });

      return () => {
        cancelled = true;
      };
    }, []),
  );

  const { from, to } = periodRange(periodKey, customFrom, customTo);

  const filteredEntries = useMemo(() => {
    if (!selectedPatientId || !from || !to) return [];

    const start = startOfDay(from).getTime();
    const end = addDays(startOfDay(to), 1).getTime() - 1;

    return entries.filter((entry) => {
      if (entry.patientId !== selectedPatientId) return false;
      const createdAt = new Date(entry.createdAt).getTime();
      return createdAt >= start && createdAt <= end;
    });
  }, [entries, selectedPatientId, from, to]);

  const fileName = `medlink_suivi_${toISODate(new Date())}.pdf`;
  const hasValidRange = Boolean(from && to && from.getTime() <= to.getTime());
  const canGenerate = hasValidRange && filteredEntries.length > 0 && !isGenerating;

  const handleCustomFromChange = (date) => {
    setCustomFrom(date);
    if (customTo && date.getTime() > customTo.getTime()) setCustomTo(date);
  };

  const handleCustomToChange = (date) => {
    setCustomTo(date);
    if (customFrom && date.getTime() < customFrom.getTime()) setCustomFrom(date);
  };

  const handleGenerate = async () => {
    setError(null);
    setIsGenerating(true);

    try {
      await downloadAndShareJournalPdf({
        patientId: selectedPatientId,
        from: toISODate(from),
        to: toISODate(to),
      });
    } catch (requestError) {
      setError(extractErrorMessage(requestError) ?? GENERIC_ERROR);
    } finally {
      setIsGenerating(false);
    }
  };

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
      </View>

      <ScrollView contentContainerStyle={styles.content}>
        <Text style={styles.title}>Export PDF</Text>

        {error && (
          <Text style={styles.error} accessibilityRole="alert">
            {error}
          </Text>
        )}

        {patients?.length > 1 && (
          <Field label="Patient">
            <View style={styles.pillRow}>
              {patients.map((patient) => (
                <Pill
                  key={patient.id}
                  label={`${patient.firstName} ${patient.lastName}`}
                  selected={selectedPatientId === patient.id}
                  onPress={() => setSelectedPatientId(patient.id)}
                  accessibilityLabel={`Patient : ${patient.firstName} ${patient.lastName}`}
                />
              ))}
            </View>
          </Field>
        )}

        <Field label="Période">
          <View style={styles.pillRow}>
            {PERIODS.map((period) => (
              <Pill
                key={period.key}
                label={period.label}
                selected={periodKey === period.key}
                onPress={() => setPeriodKey(period.key)}
                accessibilityLabel={period.label}
              />
            ))}
          </View>
        </Field>

        {periodKey === 'custom' && (
          <View style={styles.dateRow}>
            <DateField
              label="Du"
              value={customFrom}
              maximumDate={today}
              onChange={handleCustomFromChange}
              accessibilityLabel={`Date de début, ${formatDate(customFrom)}`}
            />
            <DateField
              label="Au"
              value={customTo}
              maximumDate={today}
              onChange={handleCustomToChange}
              accessibilityLabel={`Date de fin, ${formatDate(customTo)}`}
            />
          </View>
        )}

        <View
          style={styles.previewCard}
          accessible
          accessibilityRole="text"
          accessibilityLabel={
            filteredEntries.length === 0
              ? 'Aucune entrée sur cette période'
              : `${fileName}, ${filteredEntries.length} entrée${filteredEntries.length > 1 ? 's' : ''}, du ${formatDate(from)} au ${formatDate(to)}`
          }
        >
          <Text style={styles.previewIcon} accessibilityElementsHidden>
            📄
          </Text>
          <View style={styles.previewInfo}>
            <Text style={styles.previewFileName}>{fileName}</Text>
            {filteredEntries.length === 0 ? (
              <Text style={styles.previewEmpty}>Aucune entrée sur cette période.</Text>
            ) : (
              <Text style={styles.previewSummary}>
                {filteredEntries.length} entrée{filteredEntries.length > 1 ? 's' : ''} · du{' '}
                {formatDate(from)} au {formatDate(to)}
              </Text>
            )}
          </View>
        </View>

        <TouchableOpacity
          style={[styles.generateButton, !canGenerate && styles.generateButtonDisabled]}
          onPress={handleGenerate}
          disabled={!canGenerate}
          accessibilityRole="button"
          accessibilityLabel="Générer le PDF"
          accessibilityState={{ disabled: !canGenerate }}
        >
          <Text style={styles.generateButtonText}>
            {isGenerating ? 'Génération…' : '⬇️ Générer le PDF'}
          </Text>
        </TouchableOpacity>
      </ScrollView>

      <BottomNav navigation={navigation} activeKey="Export" roles={roles} logout={logout} />
    </View>
  );
}

function DateField({ label, value, maximumDate, onChange, accessibilityLabel }) {
  const [showPicker, setShowPicker] = useState(false);

  const openPicker = () => {
    if (Platform.OS === 'android') {
      DateTimePickerAndroid.open({
        value,
        mode: 'date',
        maximumDate,
        onValueChange: (event, selectedDate) => {
          if (event.type === 'set' && selectedDate) onChange(selectedDate);
        },
      });
      return;
    }

    setShowPicker(true);
  };

  return (
    <View style={styles.dateField}>
      <Text style={styles.label}>{label}</Text>
      <TouchableOpacity
        style={styles.dateButton}
        onPress={openPicker}
        accessibilityRole="button"
        accessibilityLabel={accessibilityLabel}
      >
        <Text style={styles.dateButtonText}>{formatDate(value)}</Text>
      </TouchableOpacity>

      {Platform.OS === 'ios' && showPicker && (
        <DateTimePicker
          value={value}
          mode="date"
          display="inline"
          maximumDate={maximumDate}
          onValueChange={(event, selectedDate) => {
            if (selectedDate) onChange(selectedDate);
          }}
          onDismiss={() => setShowPicker(false)}
        />
      )}
    </View>
  );
}

function Field({ label, children }) {
  return (
    <View style={styles.field}>
      <Text style={styles.label}>{label}</Text>
      {children}
    </View>
  );
}

function Pill({ label, selected, onPress, accessibilityLabel }) {
  return (
    <TouchableOpacity
      style={[styles.pill, selected && styles.pillSelected]}
      onPress={onPress}
      accessibilityRole="button"
      accessibilityState={{ selected }}
      accessibilityLabel={accessibilityLabel}
    >
      <Text style={[styles.pillText, selected && styles.pillTextSelected]}>{label}</Text>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.background },
  centered: { justifyContent: 'center', alignItems: 'center' },
  topChrome: { backgroundColor: COLORS.surface },
  content: { padding: 20, gap: 4 },
  title: { fontSize: TYPE.xl, fontWeight: '700', color: COLORS.primary, marginBottom: 16 },
  error: {
    backgroundColor: COLORS.red.bg,
    color: COLORS.red.text,
    borderRadius: 16,
    padding: 12,
    marginBottom: 16,
    fontSize: TYPE.sm,
  },
  field: { marginBottom: 20 },
  label: { fontSize: TYPE.sm, fontWeight: '600', color: COLORS.primary, marginBottom: 8 },
  pillRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  pill: {
    minHeight: 44,
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 33,
    paddingVertical: 8,
    paddingHorizontal: 16,
    backgroundColor: COLORS.surface,
  },
  pillSelected: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
  pillText: { color: COLORS.primary, fontWeight: '600', fontSize: TYPE.sm },
  pillTextSelected: { color: COLORS.onPrimary },
  dateRow: { flexDirection: 'row', gap: 12, marginBottom: 20 },
  dateField: { flex: 1 },
  dateButton: {
    minHeight: 44,
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 16,
    paddingHorizontal: 16,
    backgroundColor: COLORS.surface,
  },
  dateButtonText: { color: COLORS.primary, fontSize: TYPE.sm, fontWeight: '600' },
  previewCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    backgroundColor: COLORS.surface,
    borderRadius: 16,
    padding: 16,
    marginTop: 8,
    marginBottom: 20,
  },
  previewIcon: { fontSize: TYPE.xl },
  previewInfo: { flex: 1 },
  previewFileName: { color: COLORS.primary, fontWeight: '700', fontSize: TYPE.sm },
  previewSummary: { color: COLORS.mutedText, fontSize: TYPE.xs, marginTop: 4 },
  previewEmpty: { color: COLORS.mutedText, fontSize: TYPE.xs, marginTop: 4 },
  generateButton: {
    minHeight: 48,
    justifyContent: 'center',
    alignItems: 'center',
    borderRadius: 33,
    backgroundColor: COLORS.primary,
  },
  generateButtonDisabled: { opacity: 0.5 },
  generateButtonText: { color: COLORS.onPrimary, fontWeight: '700', fontSize: TYPE.sm },
});
