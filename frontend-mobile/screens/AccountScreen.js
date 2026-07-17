import { useCallback, useState } from 'react';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import Constants from 'expo-constants';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Modal,
  Platform,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { KeyboardAwareScrollView } from 'react-native-keyboard-aware-scroll-view';
import BottomNav from '../components/BottomNav';
import Header from '../components/Header';
import { useAuth } from '../contexts/AuthContext';
import {
  changeEmail,
  changePassword,
  deleteAccount,
  downloadAccountExport,
  fetchMe,
} from '../services/accountService';
import { COLORS, TYPE } from '../services/journalPresentation';
import { ROLE_LABELS, getPrimaryRole } from '../services/roles';

const GENERIC_LOAD_ERROR = 'Impossible de charger vos informations. Vérifiez votre connexion.';
const GENERIC_EMAIL_ERROR = "Impossible de changer l'adresse e-mail, réessayez.";
const GENERIC_PASSWORD_ERROR = 'Impossible de changer le mot de passe, réessayez.';
const GENERIC_EXPORT_ERROR = 'Impossible de générer votre export, réessayez.';
const GENERIC_DELETE_ERROR = 'Impossible de supprimer votre compte, réessayez.';

function formatDate(isoDate) {
  return new Date(isoDate).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
}

// Miroir du mapper web (AccountPage.jsx) : messages backend de
// PATCH /api/me/password (ML-67) routés vers le champ concerné.
function mapPasswordErrorMessageToField(message) {
  if (!message) return { fields: [], message };

  const missingFieldMatch = message.match(/^Le champ "(\w+)" est obligatoire\.$/);
  if (missingFieldMatch) return { fields: [missingFieldMatch[1]], message };

  if (/actuel incorrect/i.test(message)) return { fields: ['currentPassword'], message };
  if (/mot de passe doit contenir/i.test(message)) return { fields: ['newPassword'], message };

  return { fields: [], message };
}

// Miroir du mapper web (AccountPage.jsx) : messages backend de
// PATCH /api/me/email (ML-67) routés vers le champ concerné.
function mapEmailErrorMessageToField(message) {
  if (!message) return { fields: [], message };

  const missingFieldMatch = message.match(/^Le champ "(\w+)" est obligatoire\.$/);
  if (missingFieldMatch) return { fields: [missingFieldMatch[1]], message };

  if (/incorrect/i.test(message)) return { fields: ['password'], message };
  if (/email invalide|déjà utilisé/i.test(message)) return { fields: ['newEmail'], message };

  return { fields: [], message };
}

export default function AccountScreen() {
  const navigation = useNavigation();
  const { roles, firstName, logout } = useAuth();
  const primaryRole = getPrimaryRole(roles);
  const displayName = firstName ?? (primaryRole ? ROLE_LABELS[primaryRole] : 'Utilisateur');

  const [me, setMe] = useState(null);
  const [loadError, setLoadError] = useState(null);

  useFocusEffect(
    useCallback(() => {
      let cancelled = false;
      setLoadError(null);

      fetchMe()
        .then((data) => {
          if (!cancelled) setMe(data);
        })
        .catch(() => {
          if (!cancelled) setLoadError(GENERIC_LOAD_ERROR);
        });

      return () => {
        cancelled = true;
      };
    }, []),
  );

  return (
    <View style={styles.screen}>
      <View style={styles.topChrome}>
        <Header displayName={displayName} />
      </View>

      <KeyboardAwareScrollView contentContainerStyle={styles.content} enableOnAndroid>
        <Text style={styles.title}>Mon compte</Text>

        {loadError && (
          <Text style={styles.error} accessibilityRole="alert">
            {loadError}
          </Text>
        )}

        {!loadError && !me && <ActivityIndicator color={COLORS.primary} />}

        {me && (
          <>
            <MyInformationSection me={me} />
            <ChangeEmailSection onEmailChanged={logout} />
            <RgpdSection />
            <ChangePasswordSection />
            <ExportSection />
            <DeleteAccountSection onDeleted={logout} />
          </>
        )}

        <AppVersion />
      </KeyboardAwareScrollView>

      <BottomNav navigation={navigation} activeKey={null} roles={roles} logout={logout} />
    </View>
  );
}

// Numéro de version affiché (ML-89), lu depuis app.json → expo.version via
// expo-constants — même source que le update-checker (UpdateBanner, ML-98).
// Jamais codé en dur : se met à jour seul à chaque bump de app.json.
function AppVersion() {
  const version = Constants.expoConfig?.version;
  if (!version) return null;

  return (
    <Text style={styles.appVersion} accessibilityRole="text">
      {`Version de l'application : v${version}`}
    </Text>
  );
}

function Section({ title, children }) {
  return (
    <View style={styles.section}>
      <Text style={styles.sectionTitle}>{title}</Text>
      {children}
    </View>
  );
}

function MyInformationSection({ me }) {
  const primaryRole = getPrimaryRole(me.roles);

  return (
    <Section title="Mes informations">
      <InfoRow label="Adresse e-mail" value={me.email} />
      <InfoRow label="Prénom" value={me.firstName} />
      <InfoRow label="Nom" value={me.lastName} />
      <InfoRow label="Rôle" value={primaryRole ? ROLE_LABELS[primaryRole] : '—'} />
      <InfoRow label="Compte créé le" value={formatDate(me.createdAt)} last />
    </Section>
  );
}

function InfoRow({ label, value, last }) {
  return (
    <View style={[styles.infoRow, last && styles.infoRowLast]}>
      <Text style={styles.infoLabel}>{label}</Text>
      <Text style={styles.infoValue}>{value}</Text>
    </View>
  );
}

function ChangeEmailSection({ onEmailChanged }) {
  const [newEmail, setNewEmail] = useState('');
  const [password, setPassword] = useState('');
  const [fieldErrors, setFieldErrors] = useState({});
  const [bannerError, setBannerError] = useState(null);
  const [success, setSuccess] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async () => {
    setFieldErrors({});
    setBannerError(null);
    setIsSubmitting(true);

    try {
      await changeEmail({ password, newEmail });
      setSuccess(true);
      // Le token en cours est lié à l'ancien e-mail (username du JWT) : il
      // devient invalide dès la prochaine requête. On force la
      // déconnexion, en laissant le message ci-dessous le temps de
      // s'afficher plutôt que de déconnecter dans le même rendu.
      setTimeout(onEmailChanged, 1500);
    } catch (requestError) {
      const responseMessage = requestError.response?.data?.message;
      const { fields, message } = mapEmailErrorMessageToField(responseMessage);

      if (fields.length > 0) {
        const nextFieldErrors = {};
        fields.forEach((field) => {
          nextFieldErrors[field] = message;
        });
        setFieldErrors(nextFieldErrors);
      } else {
        setBannerError(message ?? GENERIC_EMAIL_ERROR);
      }
      setIsSubmitting(false);
    }
  };

  return (
    <Section title="Changer mon adresse e-mail">
      <View style={styles.field}>
        <Text style={styles.label}>Nouvelle adresse e-mail</Text>
        <TextInput
          style={styles.input}
          value={newEmail}
          onChangeText={setNewEmail}
          editable={!success}
          autoCapitalize="none"
          autoCorrect={false}
          keyboardType="email-address"
          textContentType="username"
          accessibilityLabel={
            fieldErrors.newEmail
              ? `Nouvelle adresse e-mail. Erreur : ${fieldErrors.newEmail}`
              : 'Nouvelle adresse e-mail'
          }
        />
        {fieldErrors.newEmail && <Text style={styles.fieldError}>{fieldErrors.newEmail}</Text>}
      </View>

      <FormField
        label="Mot de passe actuel"
        value={password}
        onChangeText={setPassword}
        error={fieldErrors.password}
        textContentType="password"
      />

      {bannerError && (
        <Text style={styles.error} accessibilityRole="alert">
          {bannerError}
        </Text>
      )}
      {success && (
        <Text style={styles.success} accessibilityRole="text">
          Adresse e-mail mise à jour. Vous allez être déconnecté·e pour vous reconnecter avec votre
          nouvel e-mail.
        </Text>
      )}

      <TouchableOpacity
        style={[styles.button, (isSubmitting || success) && styles.buttonDisabled]}
        onPress={handleSubmit}
        disabled={isSubmitting || success}
        accessibilityRole="button"
        accessibilityLabel="Changer mon adresse e-mail"
      >
        <Text style={styles.buttonText}>
          {isSubmitting ? 'Mise à jour…' : 'Changer mon adresse e-mail'}
        </Text>
      </TouchableOpacity>
    </Section>
  );
}

function RgpdSection() {
  const navigation = useNavigation();

  return (
    <Section title="Vos droits (RGPD)">
      <Text style={styles.rgpdText}>
        MedLink traite vos données de suivi médical (journal, messages, rendez-vous) pour coordonner
        votre parcours de soin entre patient, aidant et soignant, sur la base du consentement donné
        à l’inscription de votre compte. Vos données sont conservées pendant toute la durée
        d’utilisation du compte, et chiffrées au repos et en transit. Vous pouvez à tout moment
        consulter, exporter ou supprimer vos données personnelles depuis cet écran.
      </Text>
      <TouchableOpacity
        onPress={() => navigation.navigate('PrivacyPolicy')}
        accessibilityRole="link"
        accessibilityLabel="Consulter la politique de confidentialité"
      >
        <Text style={styles.rgpdLink}>Consulter la politique de confidentialité</Text>
      </TouchableOpacity>
    </Section>
  );
}

function ChangePasswordSection() {
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmNewPassword, setConfirmNewPassword] = useState('');
  const [fieldErrors, setFieldErrors] = useState({});
  const [bannerError, setBannerError] = useState(null);
  const [success, setSuccess] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async () => {
    setFieldErrors({});
    setBannerError(null);
    setSuccess(false);

    if (newPassword !== confirmNewPassword) {
      setFieldErrors({ confirmNewPassword: 'Les mots de passe ne correspondent pas.' });
      return;
    }

    setIsSubmitting(true);

    try {
      await changePassword({ currentPassword, newPassword });
      setCurrentPassword('');
      setNewPassword('');
      setConfirmNewPassword('');
      setSuccess(true);
    } catch (requestError) {
      const responseMessage = requestError.response?.data?.message;
      const { fields, message } = mapPasswordErrorMessageToField(responseMessage);

      if (fields.length > 0) {
        const nextFieldErrors = {};
        fields.forEach((field) => {
          nextFieldErrors[field] = message;
        });
        setFieldErrors(nextFieldErrors);
      } else {
        setBannerError(message ?? GENERIC_PASSWORD_ERROR);
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <Section title="Changer mon mot de passe">
      <FormField
        label="Mot de passe actuel"
        value={currentPassword}
        onChangeText={setCurrentPassword}
        error={fieldErrors.currentPassword}
        textContentType="password"
      />
      <FormField
        label="Nouveau mot de passe"
        value={newPassword}
        onChangeText={setNewPassword}
        error={fieldErrors.newPassword}
        textContentType="newPassword"
      />
      <FormField
        label="Confirmer le nouveau mot de passe"
        value={confirmNewPassword}
        onChangeText={setConfirmNewPassword}
        error={fieldErrors.confirmNewPassword}
        textContentType="newPassword"
      />

      {bannerError && (
        <Text style={styles.error} accessibilityRole="alert">
          {bannerError}
        </Text>
      )}
      {success && (
        <Text style={styles.success} accessibilityRole="text">
          Mot de passe mis à jour.
        </Text>
      )}

      <TouchableOpacity
        style={[styles.button, isSubmitting && styles.buttonDisabled]}
        onPress={handleSubmit}
        disabled={isSubmitting}
        accessibilityRole="button"
        accessibilityLabel="Changer mon mot de passe"
      >
        <Text style={styles.buttonText}>
          {isSubmitting ? 'Mise à jour…' : 'Changer mon mot de passe'}
        </Text>
      </TouchableOpacity>
    </Section>
  );
}

function FormField({ label, value, onChangeText, error, textContentType }) {
  return (
    <View style={styles.field}>
      <Text style={styles.label}>{label}</Text>
      <TextInput
        style={styles.input}
        value={value}
        onChangeText={onChangeText}
        secureTextEntry
        textContentType={textContentType}
        accessibilityLabel={error ? `${label}. Erreur : ${error}` : label}
      />
      {error && <Text style={styles.fieldError}>{error}</Text>}
    </View>
  );
}

function ExportSection() {
  const [isExporting, setIsExporting] = useState(false);
  const [error, setError] = useState(null);

  const handleExport = async () => {
    setError(null);
    setIsExporting(true);

    try {
      await downloadAccountExport();
    } catch {
      setError(GENERIC_EXPORT_ERROR);
    } finally {
      setIsExporting(false);
    }
  };

  return (
    <Section title="Télécharger mes données">
      <Text style={styles.sectionDescription}>
        Recevez une copie de vos données personnelles au format JSON (droit à la portabilité).
      </Text>

      {error && (
        <Text style={styles.error} accessibilityRole="alert">
          {error}
        </Text>
      )}

      <TouchableOpacity
        style={[styles.button, isExporting && styles.buttonDisabled]}
        onPress={handleExport}
        disabled={isExporting}
        accessibilityRole="button"
        accessibilityLabel="Télécharger mes données"
      >
        <Text style={styles.buttonText}>
          {isExporting ? 'Génération…' : '⬇️ Télécharger mes données'}
        </Text>
      </TouchableOpacity>
    </Section>
  );
}

function DeleteAccountSection({ onDeleted }) {
  const [isConfirming, setIsConfirming] = useState(false);
  const [password, setPassword] = useState('');
  const [error, setError] = useState(null);
  const [isDeleting, setIsDeleting] = useState(false);

  const closeConfirmation = () => {
    setIsConfirming(false);
    setPassword('');
    setError(null);
  };

  const handleDelete = async () => {
    setError(null);
    setIsDeleting(true);

    try {
      await deleteAccount({ password });
      onDeleted();
    } catch (requestError) {
      setError(requestError.response?.data?.message ?? GENERIC_DELETE_ERROR);
      setIsDeleting(false);
    }
  };

  return (
    <Section title="Supprimer mon compte">
      <Text style={styles.sectionDescription}>
        Cette action anonymise définitivement votre compte : elle ne peut pas être annulée.
      </Text>

      <TouchableOpacity
        style={styles.deleteButton}
        onPress={() => setIsConfirming(true)}
        accessibilityRole="button"
        accessibilityLabel="Supprimer mon compte"
      >
        <Text style={styles.deleteButtonText}>Supprimer mon compte</Text>
      </TouchableOpacity>

      <Modal
        visible={isConfirming}
        transparent
        animationType="fade"
        onRequestClose={closeConfirmation}
      >
        <KeyboardAvoidingView
          style={styles.overlay}
          behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        >
          <View style={styles.confirmCard} accessibilityRole="alert">
            <Text style={styles.confirmText}>
              Pour confirmer la suppression définitive de votre compte, saisissez votre mot de
              passe.
            </Text>

            <TextInput
              style={styles.confirmInput}
              value={password}
              onChangeText={setPassword}
              secureTextEntry
              placeholder="Mot de passe"
              placeholderTextColor="rgba(255,255,255,0.6)"
              accessibilityLabel="Mot de passe"
            />

            {error && (
              <Text style={styles.confirmError} accessibilityRole="alert">
                {error}
              </Text>
            )}

            <View style={styles.confirmActions}>
              <TouchableOpacity
                style={[styles.confirmDeleteButton, isDeleting && styles.buttonDisabled]}
                onPress={handleDelete}
                disabled={isDeleting}
                accessibilityRole="button"
                accessibilityLabel="Confirmer la suppression"
              >
                <Text style={styles.confirmDeleteText}>
                  {isDeleting ? 'Suppression…' : 'Confirmer'}
                </Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={styles.confirmCancelButton}
                onPress={closeConfirmation}
                disabled={isDeleting}
                accessibilityRole="button"
                accessibilityLabel="Annuler"
              >
                <Text style={styles.confirmCancelText}>Annuler</Text>
              </TouchableOpacity>
            </View>
          </View>
        </KeyboardAvoidingView>
      </Modal>
    </Section>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.background },
  topChrome: { backgroundColor: COLORS.surface },
  content: { padding: 20, gap: 16 },
  title: { fontSize: TYPE.xl, fontWeight: '700', color: COLORS.primary, marginBottom: 4 },
  error: {
    backgroundColor: COLORS.red.bg,
    color: COLORS.red.text,
    borderRadius: 16,
    padding: 12,
    fontSize: TYPE.sm,
  },
  success: {
    backgroundColor: COLORS.green.bg,
    color: COLORS.green.text,
    borderRadius: 16,
    padding: 12,
    fontSize: TYPE.sm,
    fontWeight: '600',
  },
  section: {
    backgroundColor: COLORS.surface,
    borderRadius: 16,
    padding: 20,
    gap: 12,
  },
  sectionTitle: { fontSize: TYPE.md, fontWeight: '700', color: COLORS.primary },
  sectionDescription: { fontSize: TYPE.sm, color: COLORS.mutedText },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 12,
    paddingBottom: 10,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  infoRowLast: { borderBottomWidth: 0, paddingBottom: 0 },
  infoLabel: { fontSize: TYPE.sm, color: COLORS.mutedText, fontWeight: '600' },
  infoValue: { fontSize: TYPE.sm, color: COLORS.primary, fontWeight: '700', flexShrink: 1 },
  rgpdText: { fontSize: TYPE.sm, color: COLORS.primary, lineHeight: 20 },
  appVersion: {
    fontSize: TYPE.xs,
    color: COLORS.mutedText,
    textAlign: 'center',
    marginTop: 4,
  },
  rgpdLink: {
    fontSize: TYPE.sm,
    color: COLORS.primary,
    fontWeight: '600',
    textDecorationLine: 'underline',
    minHeight: 44,
    textAlignVertical: 'center',
  },
  field: { gap: 8 },
  label: { fontSize: TYPE.sm, fontWeight: '600', color: COLORS.primary },
  input: {
    minHeight: 44,
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 16,
    paddingHorizontal: 16,
    fontSize: TYPE.base,
    color: COLORS.primary,
  },
  fieldError: { color: COLORS.red.text, fontSize: TYPE.xs },
  button: {
    minHeight: 48,
    justifyContent: 'center',
    alignItems: 'center',
    borderRadius: 33,
    backgroundColor: COLORS.primary,
  },
  buttonDisabled: { opacity: 0.5 },
  buttonText: { color: COLORS.onPrimary, fontWeight: '700', fontSize: TYPE.sm },
  deleteButton: {
    minHeight: 48,
    justifyContent: 'center',
    alignItems: 'center',
    borderRadius: 33,
    backgroundColor: COLORS.red.text,
  },
  deleteButtonText: { color: COLORS.onPrimary, fontWeight: '700', fontSize: TYPE.sm },
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.4)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  confirmCard: {
    backgroundColor: COLORS.primary,
    borderRadius: 16,
    padding: 24,
    width: '100%',
    maxWidth: 360,
    gap: 12,
  },
  confirmText: { color: COLORS.onPrimary, fontSize: TYPE.sm },
  confirmInput: {
    minHeight: 44,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.5)',
    borderRadius: 16,
    paddingHorizontal: 16,
    color: COLORS.onPrimary,
    fontSize: TYPE.base,
  },
  confirmError: {
    backgroundColor: 'rgba(253,232,236,0.9)',
    color: COLORS.red.text,
    borderRadius: 12,
    padding: 10,
    fontSize: TYPE.xs,
  },
  confirmActions: { flexDirection: 'row', gap: 12, justifyContent: 'flex-end' },
  confirmDeleteButton: {
    backgroundColor: COLORS.onPrimary,
    borderRadius: 33,
    paddingVertical: 8,
    paddingHorizontal: 16,
  },
  confirmDeleteText: { color: COLORS.red.text, fontWeight: '700' },
  confirmCancelButton: {
    borderRadius: 33,
    paddingVertical: 8,
    paddingHorizontal: 16,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.5)',
  },
  confirmCancelText: { color: COLORS.onPrimary, fontWeight: '600' },
});
