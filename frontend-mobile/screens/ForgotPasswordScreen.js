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
import { requestPasswordReset } from '../services/authService';

const GENERIC_ERROR = "Impossible d'envoyer la demande, réessayez.";

export default function ForgotPasswordScreen({ navigation }) {
  const [email, setEmail] = useState('');
  const [error, setError] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [confirmationMessage, setConfirmationMessage] = useState(null);

  const handleSubmit = async () => {
    setError(null);
    setIsSubmitting(true);

    try {
      const { message } = await requestPasswordReset(email);
      setConfirmationMessage(message);
    } catch (requestError) {
      if (requestError.response?.status === 429) {
        setError(requestError.response.data?.message ?? 'Trop de demandes, réessayez plus tard.');
      } else {
        setError(GENERIC_ERROR);
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
          <Text style={styles.subtitle}>Mot de passe oublié</Text>

          {confirmationMessage ? (
            <Text style={styles.success} accessibilityRole="text">
              {confirmationMessage}
            </Text>
          ) : (
            <>
              <View style={styles.field}>
                <Text style={styles.label}>Adresse e-mail</Text>
                <TextInput
                  style={styles.input}
                  value={email}
                  onChangeText={setEmail}
                  accessibilityLabel="Adresse e-mail"
                  autoCapitalize="none"
                  autoCorrect={false}
                  keyboardType="email-address"
                  textContentType="username"
                  returnKeyType="done"
                  onSubmitEditing={handleSubmit}
                />
              </View>

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
                accessibilityLabel="Envoyer le lien de réinitialisation"
              >
                <Text style={styles.submitText}>
                  {isSubmitting ? 'Envoi…' : 'Envoyer le lien de réinitialisation'}
                </Text>
              </TouchableOpacity>
            </>
          )}

          <TouchableOpacity
            onPress={() => navigation.navigate('ResetPassword')}
            accessibilityRole="link"
            accessibilityLabel="J'ai déjà un code de réinitialisation"
          >
            <Text style={styles.secondaryLink}>J'ai déjà un code</Text>
          </TouchableOpacity>

          <TouchableOpacity
            onPress={() => navigation.navigate('Login')}
            accessibilityRole="link"
            accessibilityLabel="Retour à la connexion"
          >
            <Text style={styles.secondaryLink}>Retour à la connexion</Text>
          </TouchableOpacity>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
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
  container: {
    flexGrow: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
  },
  card: {
    width: '100%',
    maxWidth: 420,
    backgroundColor: COLORS.surface,
    borderRadius: 16,
    padding: 32,
  },
  title: {
    fontSize: 28,
    fontWeight: '700',
    color: COLORS.primary,
    marginBottom: 4,
  },
  subtitle: {
    fontSize: 16,
    color: COLORS.textMuted,
    marginBottom: 32,
  },
  field: { marginBottom: 20 },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: COLORS.text,
    marginBottom: 8,
  },
  input: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 33,
    paddingHorizontal: 16,
    paddingVertical: 12,
    fontSize: 16,
    color: COLORS.text,
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
  submitText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '700',
  },
  success: {
    backgroundColor: '#E3F5EA',
    color: '#1E7E46',
    borderRadius: 16,
    padding: 12,
    marginBottom: 16,
    fontSize: 14,
    fontWeight: '600',
  },
  secondaryLink: {
    marginTop: 16,
    textAlign: 'center',
    color: COLORS.primaryLight,
    fontWeight: '600',
  },
});
