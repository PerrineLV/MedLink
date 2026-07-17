import { useState } from 'react';
import { StyleSheet, Text, TextInput, TouchableOpacity, View } from 'react-native';
import { KeyboardAwareScrollView } from 'react-native-keyboard-aware-scroll-view';
import { useAuth } from '../contexts/AuthContext';

const GENERIC_ERROR = 'Identifiants incorrects';

export default function LoginScreen({ navigation, route }) {
  const { login } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const registered = route?.params?.registered === true;
  const passwordReset = route?.params?.passwordReset === true;

  const handleSubmit = async () => {
    setError(null);
    setIsSubmitting(true);

    try {
      await login(email, password);
    } catch (requestError) {
      // 429 = rate-limited (ML-20): surface that explicit reason. Anything
      // else (bad password, unknown email...) must stay generic so we don't
      // leak whether the account exists.
      if (requestError.response?.status === 429) {
        setError(requestError.response.data?.message ?? 'Trop de tentatives, réessayez plus tard.');
      } else {
        setError(GENERIC_ERROR);
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
        <Text style={styles.subtitle}>Connectez-vous à votre espace</Text>

        {registered && (
          <Text style={styles.success} accessibilityRole="text">
            Compte créé, vous pouvez vous connecter.
          </Text>
        )}

        {passwordReset && (
          <Text style={styles.success} accessibilityRole="text">
            Mot de passe réinitialisé, vous pouvez vous connecter.
          </Text>
        )}

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
            returnKeyType="next"
          />
        </View>

        <View style={styles.field}>
          <Text style={styles.label}>Mot de passe</Text>
          <TextInput
            style={styles.input}
            value={password}
            onChangeText={setPassword}
            accessibilityLabel="Mot de passe"
            secureTextEntry
            textContentType="password"
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
          accessibilityLabel="Se connecter"
        >
          <Text style={styles.submitText}>{isSubmitting ? 'Connexion…' : 'Se connecter'}</Text>
        </TouchableOpacity>

        <TouchableOpacity
          onPress={() => navigation.navigate('ForgotPassword')}
          accessibilityRole="link"
          accessibilityLabel="Mot de passe oublié"
        >
          <Text style={styles.forgotPasswordLink}>Mot de passe oublié ?</Text>
        </TouchableOpacity>

        <TouchableOpacity
          onPress={() => navigation.navigate('Register')}
          accessibilityRole="link"
          accessibilityLabel="Pas encore de compte : en créer un"
        >
          <Text style={styles.registerLink}>Pas encore de compte ? Créer un compte</Text>
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
    backgroundColor: COLORS.surface,
    overflow: 'hidden',
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
  forgotPasswordLink: {
    marginTop: 16,
    textAlign: 'center',
    color: COLORS.primaryLight,
    fontWeight: '600',
  },
  registerLink: {
    marginTop: 8,
    textAlign: 'center',
    color: COLORS.primaryLight,
    fontWeight: '600',
  },
});
