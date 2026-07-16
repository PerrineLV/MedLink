import { useState } from 'react';
import { StyleSheet, Text, TextInput, TouchableOpacity, View } from 'react-native';
import { KeyboardAwareScrollView } from 'react-native-keyboard-aware-scroll-view';
import { confirmPasswordReset } from '../services/authService';

const GENERIC_ERROR = 'Impossible de réinitialiser le mot de passe, réessayez.';

export default function ResetPasswordScreen({ navigation, route }) {
  const [token, setToken] = useState(route?.params?.token ?? '');
  const [newPassword, setNewPassword] = useState('');
  const [confirmNewPassword, setConfirmNewPassword] = useState('');
  const [fieldErrors, setFieldErrors] = useState({});
  const [bannerError, setBannerError] = useState(null);
  const [linkExpired, setLinkExpired] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async () => {
    setFieldErrors({});
    setBannerError(null);
    setLinkExpired(false);

    if (newPassword !== confirmNewPassword) {
      setFieldErrors({ confirmNewPassword: 'Les mots de passe ne correspondent pas.' });
      return;
    }

    setIsSubmitting(true);

    try {
      await confirmPasswordReset(token.trim(), newPassword);
      navigation.navigate('Login', { passwordReset: true });
    } catch (requestError) {
      const status = requestError.response?.status;
      const message = requestError.response?.data?.message;

      if (status === 410) {
        setLinkExpired(true);
        setBannerError(message ?? GENERIC_ERROR);
      } else if (message && /mot de passe doit contenir/i.test(message)) {
        setFieldErrors({ newPassword: message });
      } else {
        setBannerError(message ?? GENERIC_ERROR);
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <KeyboardAwareScrollView
      style={styles.flexFill}
      contentContainerStyle={styles.container}
      keyboardShouldPersistTaps="handled"
      enableOnAndroid
    >
      <View style={styles.card}>
        <Text style={styles.title}>MedLink</Text>
        <Text style={styles.subtitle}>Réinitialiser le mot de passe</Text>

        <View style={styles.field}>
          <Text style={styles.label}>Code reçu par email</Text>
          <TextInput
            style={styles.input}
            value={token}
            onChangeText={setToken}
            accessibilityLabel="Code reçu par email"
            autoCapitalize="none"
            autoCorrect={false}
            returnKeyType="next"
          />
        </View>

        <View style={styles.field}>
          <Text style={styles.label}>Nouveau mot de passe</Text>
          <TextInput
            style={styles.input}
            value={newPassword}
            onChangeText={setNewPassword}
            accessibilityLabel="Nouveau mot de passe"
            secureTextEntry
            textContentType="newPassword"
            returnKeyType="next"
          />
          {fieldErrors.newPassword && (
            <Text style={styles.fieldError} accessibilityRole="alert">
              {fieldErrors.newPassword}
            </Text>
          )}
        </View>

        <View style={styles.field}>
          <Text style={styles.label}>Confirmer le nouveau mot de passe</Text>
          <TextInput
            style={styles.input}
            value={confirmNewPassword}
            onChangeText={setConfirmNewPassword}
            accessibilityLabel="Confirmer le nouveau mot de passe"
            secureTextEntry
            textContentType="newPassword"
            returnKeyType="done"
            onSubmitEditing={handleSubmit}
          />
          {fieldErrors.confirmNewPassword && (
            <Text style={styles.fieldError} accessibilityRole="alert">
              {fieldErrors.confirmNewPassword}
            </Text>
          )}
        </View>

        {bannerError && (
          <Text style={styles.error} accessibilityRole="alert">
            {bannerError}
          </Text>
        )}

        {linkExpired && (
          <TouchableOpacity
            onPress={() => navigation.navigate('ForgotPassword')}
            accessibilityRole="link"
            accessibilityLabel="Refaire une demande de réinitialisation"
          >
            <Text style={styles.secondaryLink}>Refaire une demande</Text>
          </TouchableOpacity>
        )}

        <TouchableOpacity
          style={[styles.submit, isSubmitting && styles.submitDisabled]}
          onPress={handleSubmit}
          disabled={isSubmitting}
          accessibilityRole="button"
          accessibilityLabel="Réinitialiser le mot de passe"
        >
          <Text style={styles.submitText}>
            {isSubmitting ? 'Réinitialisation…' : 'Réinitialiser le mot de passe'}
          </Text>
        </TouchableOpacity>

        <TouchableOpacity
          onPress={() => navigation.navigate('Login')}
          accessibilityRole="link"
          accessibilityLabel="Retour à la connexion"
        >
          <Text style={styles.secondaryLink}>Retour à la connexion</Text>
        </TouchableOpacity>
      </View>
    </KeyboardAwareScrollView>
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
  fieldError: {
    color: COLORS.danger,
    fontSize: 13,
    marginTop: 6,
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
    marginTop: 4,
  },
  submitDisabled: { opacity: 0.6 },
  submitText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '700',
  },
  secondaryLink: {
    marginTop: 16,
    textAlign: 'center',
    color: COLORS.primaryLight,
    fontWeight: '600',
  },
});
