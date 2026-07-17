import { useCallback, useState } from 'react';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import {
  ActivityIndicator,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { KeyboardAwareScrollView } from 'react-native-keyboard-aware-scroll-view';
import { createAppointment } from '../services/appointmentService';
import { fetchPatients } from '../services/patientService';

const COLORS = {
  primary: '#2E3862',
  primaryLight: '#3B5BDB',
  background: '#F4F6FB',
  surface: '#FFFFFF',
  text: '#1C2338',
  textMuted: '#5B6178',
  border: '#E1E4EF',
  danger: '#C1352B',
  dangerBg: '#FBECEB',
};

const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/;
const TIME_PATTERN = /^([01]\d|2[0-3]):([0-5]\d)$/;
const GENERIC_ERROR = "Impossible d'enregistrer ce rendez-vous, réessayez.";

export default function NewAppointmentScreen() {
  const navigation = useNavigation();

  const [patients, setPatients] = useState(null);
  const [selectedPatientId, setSelectedPatientId] = useState(null);
  const [date, setDate] = useState('');
  const [time, setTime] = useState('');
  const [consultationType, setConsultationType] = useState('');
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

    if (!DATE_PATTERN.test(date) || !TIME_PATTERN.test(time)) {
      setError('Indiquez une date (AAAA-MM-JJ) et une heure (HH:MM) valides.');
      return;
    }

    const scheduledAt = new Date(`${date}T${time}:00`);
    if (Number.isNaN(scheduledAt.getTime()) || scheduledAt.getTime() < Date.now()) {
      setError('La date du rendez-vous ne peut pas être dans le passé.');
      return;
    }

    setIsSubmitting(true);
    try {
      await createAppointment({
        patientId: selectedPatientId,
        scheduledAt: scheduledAt.toISOString(),
        notes: consultationType.trim(),
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
        <Text style={styles.emptyText}>Aucun patient disponible pour créer un rendez-vous.</Text>
        <BackButton navigation={navigation} />
      </View>
    );
  }

  return (
    <KeyboardAwareScrollView
      style={styles.flexFill}
      contentContainerStyle={styles.container}
      keyboardShouldPersistTaps="handled"
      enableOnAndroid
    >
      <View style={styles.header}>
        <BackButton navigation={navigation} />
        <Text style={styles.title}>Nouveau RDV</Text>
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

      <Field label="Date">
        <TextInput
          style={styles.input}
          value={date}
          onChangeText={setDate}
          placeholder="AAAA-MM-JJ"
          maxLength={10}
          keyboardType="numbers-and-punctuation"
          accessibilityLabel="Date du rendez-vous, format AAAA-MM-JJ"
        />
      </Field>

      <Field label="Heure">
        <TextInput
          style={styles.input}
          value={time}
          onChangeText={setTime}
          placeholder="HH:MM"
          maxLength={5}
          keyboardType="numbers-and-punctuation"
          accessibilityLabel="Heure du rendez-vous, format HH:MM"
        />
      </Field>

      <Field label="Type de consultation (optionnel)">
        <TextInput
          style={styles.input}
          value={consultationType}
          onChangeText={setConsultationType}
          maxLength={255}
          placeholder="Ex. Contrôle de routine"
          accessibilityLabel="Type de consultation"
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
        accessibilityLabel="Enregistrer le rendez-vous"
      >
        <Text style={styles.submitText}>{isSubmitting ? 'Enregistrement…' : 'Enregistrer'}</Text>
      </TouchableOpacity>
    </KeyboardAwareScrollView>
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
