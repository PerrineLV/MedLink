import { useCallback, useState } from 'react';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { createJournalEntry } from '../services/journalEntryService';
import { fetchPatients } from '../services/patientService';

const COLORS = {
  primary: '#2E3862',
  primaryLight: '#7491F7',
  background: '#F4F6FB',
  surface: '#FFFFFF',
  text: '#1C2338',
  textMuted: '#5B6178',
  border: '#E1E4EF',
  danger: '#C1352B',
  dangerBg: '#FBECEB',
};

const MOOD_OPTIONS = [1, 2, 3, 4, 5];
const PAIN_OPTIONS = Array.from({ length: 11 }, (_, painLevel) => painLevel);
const BLOOD_PRESSURE_PATTERN = /^\d{1,3}$/;
const GENERIC_ERROR = "Impossible d'enregistrer cette entrée, réessayez.";

export default function NewEntryScreen() {
  const navigation = useNavigation();

  const [patients, setPatients] = useState(null);
  const [selectedPatientId, setSelectedPatientId] = useState(null);
  const [mood, setMood] = useState(3);
  const [painLevel, setPainLevel] = useState(0);
  const [systolic, setSystolic] = useState('');
  const [diastolic, setDiastolic] = useState('');
  const [note, setNote] = useState('');
  const [error, setError] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useFocusEffect(
    useCallback(() => {
      let cancelled = false;

      fetchPatients()
        .then((fetchedPatients) => {
          if (cancelled) return;
          setPatients(fetchedPatients);
          setSelectedPatientId((current) => current ?? fetchedPatients[0]?.id ?? null);
        })
        .catch(() => {
          if (!cancelled) setPatients([]);
        });

      return () => {
        cancelled = true;
      };
    }, []),
  );

  const handleSubmit = async () => {
    setError(null);

    if (!BLOOD_PRESSURE_PATTERN.test(systolic) || !BLOOD_PRESSURE_PATTERN.test(diastolic)) {
      setError('La tension doit être au format "120/80".');
      return;
    }

    setIsSubmitting(true);
    try {
      await createJournalEntry({
        patientId: selectedPatientId,
        mood,
        painLevel,
        bloodPressure: `${systolic}/${diastolic}`,
        note: note.trim(),
      });
      navigation.goBack();
    } catch (requestError) {
      setError(requestError.response?.data?.detail ?? GENERIC_ERROR);
    } finally {
      setIsSubmitting(false);
    }
  };

  if (patients === null) {
    return (
      <View style={[styles.container, styles.centered]}>
        <ActivityIndicator color={COLORS.primary} size="large" />
      </View>
    );
  }

  if (patients.length === 0) {
    return (
      <View style={[styles.container, styles.centered]}>
        <Text style={styles.emptyText}>Aucun patient disponible pour créer une entrée.</Text>
        <BackButton navigation={navigation} />
      </View>
    );
  }

  return (
    <KeyboardAvoidingView
      style={styles.flexFill}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <ScrollView contentContainerStyle={styles.container} keyboardShouldPersistTaps="handled">
        <View style={styles.header}>
          <BackButton navigation={navigation} />
          <Text style={styles.title}>Nouvelle entrée</Text>
        </View>

        {patients.length > 1 && (
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

        <Field label="Humeur">
          <View style={styles.pillRow}>
            {MOOD_OPTIONS.map((value) => (
              <Pill
                key={value}
                label={String(value)}
                selected={mood === value}
                onPress={() => setMood(value)}
                accessibilityLabel={`Humeur : ${value} sur 5`}
              />
            ))}
          </View>
        </Field>

        <Field label="Douleur">
          <ScrollView horizontal showsHorizontalScrollIndicator={false}>
            <View style={styles.pillRow}>
              {PAIN_OPTIONS.map((value) => (
                <Pill
                  key={value}
                  label={String(value)}
                  selected={painLevel === value}
                  onPress={() => setPainLevel(value)}
                  accessibilityLabel={`Douleur : ${value} sur 10`}
                />
              ))}
            </View>
          </ScrollView>
        </Field>

        <Field label="Tension artérielle">
          <View style={styles.bloodPressureRow}>
            <TextInput
              style={[styles.input, styles.bloodPressureInput]}
              value={systolic}
              onChangeText={setSystolic}
              keyboardType="number-pad"
              maxLength={3}
              placeholder="120"
              accessibilityLabel="Tension systolique"
            />
            <Text style={styles.bloodPressureSeparator}>/</Text>
            <TextInput
              style={[styles.input, styles.bloodPressureInput]}
              value={diastolic}
              onChangeText={setDiastolic}
              keyboardType="number-pad"
              maxLength={3}
              placeholder="80"
              accessibilityLabel="Tension diastolique"
            />
          </View>
        </Field>

        <Field label="Note (optionnelle)">
          <TextInput
            style={[styles.input, styles.noteInput]}
            value={note}
            onChangeText={setNote}
            multiline
            maxLength={1000}
            placeholder="Précisions sur la journée…"
            accessibilityLabel="Note"
          />
        </Field>

        {error && (
          <Text style={styles.error} accessibilityRole="alert">
            {error}
          </Text>
        )}

        <TouchableOpacity
          style={[styles.submit, isSubmitting && styles.submitDisabled]}
          onPress={handleSubmit}
          disabled={isSubmitting}
          accessibilityRole="button"
          accessibilityLabel="Enregistrer l'entrée"
        >
          <Text style={styles.submitText}>{isSubmitting ? 'Enregistrement…' : 'Enregistrer'}</Text>
        </TouchableOpacity>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

function BackButton({ navigation }) {
  return (
    <TouchableOpacity
      onPress={() => navigation.goBack()}
      accessibilityRole="button"
      accessibilityLabel="Retour"
      style={styles.backButton}
    >
      <Text style={styles.backButtonText}>‹ Retour</Text>
    </TouchableOpacity>
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
  flexFill: { flex: 1, backgroundColor: COLORS.background },
  container: { flexGrow: 1, backgroundColor: COLORS.background, padding: 24, paddingTop: 60 },
  centered: { justifyContent: 'center', alignItems: 'center' },
  header: { flexDirection: 'row', alignItems: 'center', marginBottom: 24, gap: 12 },
  backButton: { paddingVertical: 4 },
  backButtonText: { color: COLORS.primary, fontSize: 16, fontWeight: '600' },
  title: { fontSize: 22, fontWeight: '700', color: COLORS.primary },
  emptyText: { color: COLORS.textMuted, fontSize: 15, textAlign: 'center', marginBottom: 16 },
  field: { marginBottom: 20 },
  label: { fontSize: 14, fontWeight: '600', color: COLORS.text, marginBottom: 8 },
  pillRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  pill: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 33,
    paddingVertical: 8,
    paddingHorizontal: 16,
    backgroundColor: COLORS.surface,
  },
  pillSelected: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
  pillText: { color: COLORS.text, fontWeight: '600' },
  pillTextSelected: { color: '#fff' },
  input: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 16,
    paddingHorizontal: 16,
    paddingVertical: 12,
    fontSize: 16,
    color: COLORS.text,
    backgroundColor: COLORS.surface,
  },
  bloodPressureRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  bloodPressureInput: { flex: 1, textAlign: 'center' },
  bloodPressureSeparator: { fontSize: 20, fontWeight: '700', color: COLORS.textMuted },
  noteInput: { minHeight: 90, textAlignVertical: 'top' },
  error: {
    backgroundColor: COLORS.dangerBg,
    color: COLORS.danger,
    borderRadius: 16,
    padding: 12,
    marginBottom: 16,
    fontSize: 14,
  },
  submit: {
    backgroundColor: COLORS.primaryLight,
    borderRadius: 33,
    paddingVertical: 14,
    alignItems: 'center',
  },
  submitDisabled: { opacity: 0.6 },
  submitText: { color: '#fff', fontSize: 16, fontWeight: '700' },
});
