import { useState } from 'react';
import {
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { register } from '../services/authService';

const ROLE_OPTIONS = [
  { value: 'patient', label: 'Patient' },
  { value: 'aidant', label: 'Aidant' },
  { value: 'soignant', label: 'Soignant' },
];

const GENERIC_ERROR = 'Impossible de créer le compte, réessayez.';

export default function RegisterScreen({ navigation }) {
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [role, setRole] = useState(null);
  const [title, setTitle] = useState('');
  const [consent, setConsent] = useState(false);
  const [fieldErrors, setFieldErrors] = useState({});
  const [bannerError, setBannerError] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async () => {
    setFieldErrors({});
    setBannerError(null);
    setIsSubmitting(true);

    try {
      await register({
        email,
        password,
        firstName,
        lastName,
        role,
        title: role === 'soignant' ? title : undefined,
        consent,
      });
      navigation.navigate('Login', { registered: true });
    } catch (requestError) {
      const responseMessage = requestError.response?.data?.message;
      const { fields, message } = mapRegistrationErrorMessageToField(responseMessage);

      if (fields.length > 0) {
        const nextFieldErrors = {};
        fields.forEach((field) => {
          nextFieldErrors[field] = message;
        });
        setFieldErrors(nextFieldErrors);
      } else {
        setBannerError(message ?? GENERIC_ERROR);
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <KeyboardAvoidingView
      style={styles.flexFill}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <ScrollView contentContainerStyle={styles.container} keyboardShouldPersistTaps="handled">
        <View style={styles.card}>
          <Text style={styles.title}>MedLink</Text>
          <Text style={styles.subtitle}>Créer votre compte</Text>

          <View style={styles.field}>
            <Text style={styles.label}>Prénom</Text>
            <TextInput
              style={styles.input}
              value={firstName}
              onChangeText={setFirstName}
              accessibilityLabel={
                fieldErrors.firstName ? `Prénom. Erreur : ${fieldErrors.firstName}` : 'Prénom'
              }
              autoCapitalize="words"
              textContentType="givenName"
              returnKeyType="next"
            />
            {fieldErrors.firstName && (
              <Text style={styles.fieldError}>{fieldErrors.firstName}</Text>
            )}
          </View>

          <View style={styles.field}>
            <Text style={styles.label}>Nom</Text>
            <TextInput
              style={styles.input}
              value={lastName}
              onChangeText={setLastName}
              accessibilityLabel={
                fieldErrors.lastName ? `Nom. Erreur : ${fieldErrors.lastName}` : 'Nom'
              }
              autoCapitalize="words"
              textContentType="familyName"
              returnKeyType="next"
            />
            {fieldErrors.lastName && <Text style={styles.fieldError}>{fieldErrors.lastName}</Text>}
          </View>

          <View style={styles.field}>
            <Text style={styles.label}>Adresse e-mail</Text>
            <TextInput
              style={styles.input}
              value={email}
              onChangeText={setEmail}
              accessibilityLabel={
                fieldErrors.email
                  ? `Adresse e-mail. Erreur : ${fieldErrors.email}`
                  : 'Adresse e-mail'
              }
              autoCapitalize="none"
              autoCorrect={false}
              keyboardType="email-address"
              textContentType="username"
              returnKeyType="next"
            />
            {fieldErrors.email && <Text style={styles.fieldError}>{fieldErrors.email}</Text>}
          </View>

          <View style={styles.field}>
            <Text style={styles.label}>Mot de passe</Text>
            <TextInput
              style={styles.input}
              value={password}
              onChangeText={setPassword}
              accessibilityLabel={
                fieldErrors.password
                  ? `Mot de passe. Erreur : ${fieldErrors.password}`
                  : 'Mot de passe'
              }
              secureTextEntry
              textContentType="newPassword"
              returnKeyType="next"
            />
            {fieldErrors.password && <Text style={styles.fieldError}>{fieldErrors.password}</Text>}
          </View>

          <Field label="Vous êtes">
            <View style={styles.pillRow}>
              {ROLE_OPTIONS.map((option) => (
                <Pill
                  key={option.value}
                  label={option.label}
                  selected={role === option.value}
                  onPress={() => setRole(option.value)}
                  accessibilityLabel={`Rôle : ${option.label}`}
                />
              ))}
            </View>
            {fieldErrors.role && <Text style={styles.fieldError}>{fieldErrors.role}</Text>}
          </Field>

          {role === 'soignant' && (
            <View style={styles.field}>
              <Text style={styles.label}>Titre (ex. Dr, Pr)</Text>
              <TextInput
                style={styles.input}
                value={title}
                onChangeText={setTitle}
                accessibilityLabel="Titre (ex. Dr, Pr)"
              />
            </View>
          )}

          <View style={styles.field}>
            <TouchableOpacity
              style={styles.consentRow}
              onPress={() => setConsent((current) => !current)}
              accessibilityRole="checkbox"
              accessibilityState={{ checked: consent }}
              accessibilityLabel={
                fieldErrors.consent
                  ? `J'accepte le traitement de mes données de santé. Erreur : ${fieldErrors.consent}`
                  : "J'accepte le traitement de mes données de santé"
              }
            >
              <View style={[styles.checkbox, consent && styles.checkboxChecked]}>
                {consent && <Text style={styles.checkboxMark}>✓</Text>}
              </View>
              <Text style={styles.consentText}>
                J’accepte que mes données de santé soient traitées dans le cadre de MedLink.
              </Text>
            </TouchableOpacity>
            {fieldErrors.consent && <Text style={styles.fieldError}>{fieldErrors.consent}</Text>}
          </View>

          {bannerError && (
            <Text style={styles.error} accessibilityRole="alert">
              {bannerError}
            </Text>
          )}

          <TouchableOpacity
            style={[styles.submit, (isSubmitting || !consent) && styles.submitDisabled]}
            onPress={handleSubmit}
            disabled={isSubmitting || !consent}
            accessibilityRole="button"
            accessibilityLabel="Créer mon compte"
          >
            <Text style={styles.submitText}>
              {isSubmitting ? 'Création du compte…' : 'Créer mon compte'}
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            onPress={() => navigation.navigate('Login')}
            accessibilityRole="link"
            accessibilityLabel="Déjà un compte : se connecter"
          >
            <Text style={styles.loginLink}>Déjà un compte ? Se connecter</Text>
          </TouchableOpacity>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
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

// Route un message d'erreur backend (POST /api/auth/register) vers le(s)
// champ(s) de formulaire concerné(s). fields: [] => bannière générique
// (429, réseau, message non reconnu). Miroir de la version web
// (RegisterPage.jsx) — dupliquée volontairement, comme le reste des
// services de ce projet.
function mapRegistrationErrorMessageToField(message) {
  if (!message) {
    return { fields: [], message };
  }

  const missingFieldMatch = message.match(/^Le champ "(\w+)" est obligatoire\.$/);
  if (missingFieldMatch) {
    return { fields: [missingFieldMatch[1]], message };
  }

  if (/rôle/i.test(message)) {
    return { fields: ['role'], message };
  }

  if (/email/i.test(message)) {
    return { fields: ['email'], message };
  }

  if (/mot de passe/i.test(message)) {
    return { fields: ['password'], message };
  }

  if (/consentement/i.test(message)) {
    return { fields: ['consent'], message };
  }

  if (/prénom et le nom/i.test(message)) {
    return { fields: ['firstName', 'lastName'], message };
  }

  return { fields: [], message };
}

const COLORS = {
  primary: '#2E3862',
  primaryLight: '#7491F7',
  surface: '#FFFFFF',
  text: '#1C2338',
  textMuted: '#5B6178',
  border: '#E1E4EF',
  danger: '#C1352B',
  dangerBg: '#FBECEB',
};

const styles = StyleSheet.create({
  flexFill: { flex: 1, backgroundColor: COLORS.primary },
  container: { flexGrow: 1, alignItems: 'center', justifyContent: 'center', padding: 24 },
  card: {
    width: '100%',
    maxWidth: 420,
    backgroundColor: COLORS.surface,
    borderRadius: 16,
    padding: 32,
  },
  title: { fontSize: 28, fontWeight: '700', color: COLORS.primary, marginBottom: 4 },
  subtitle: { fontSize: 16, color: COLORS.textMuted, marginBottom: 32 },
  field: { marginBottom: 20 },
  label: { fontSize: 14, fontWeight: '600', color: COLORS.text, marginBottom: 8 },
  input: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 33,
    paddingHorizontal: 16,
    paddingVertical: 12,
    fontSize: 16,
    color: COLORS.text,
  },
  fieldError: { color: COLORS.danger, fontSize: 13, marginTop: 6 },
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
  consentRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 10 },
  checkbox: {
    width: 22,
    height: 22,
    borderRadius: 4,
    borderWidth: 1,
    borderColor: COLORS.border,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 2,
  },
  checkboxChecked: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
  checkboxMark: { color: '#fff', fontSize: 14, fontWeight: '700' },
  consentText: { flex: 1, fontSize: 14, color: COLORS.text },
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
  loginLink: { marginTop: 16, textAlign: 'center', color: COLORS.primaryLight, fontWeight: '600' },
});
