import { Alert, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import Header from '../components/Header';
import { useAuth } from '../contexts/AuthContext';
import { COLORS, TYPE } from '../services/journalPresentation';

function confirmLogout(logout) {
  Alert.alert('Se déconnecter', 'Voulez-vous vous déconnecter ?', [
    { text: 'Annuler', style: 'cancel' },
    { text: 'Se déconnecter', style: 'destructive', onPress: logout },
  ]);
}

// Dedicated dead-end screen for an admin-only session (ML-73): admin has no
// mobile interface, so this deliberately renders no BottomNav and no link to
// any of the Journal/Messages/RDV/Export/Profil screens — App.js doesn't
// even register those routes for this session, this is just the one screen
// there is to show.
export default function AdminBlockedScreen() {
  const { firstName, logout } = useAuth();

  return (
    <View style={styles.screen}>
      <Header displayName={firstName ?? 'Administrateur'} />

      <View style={styles.content}>
        <Text
          style={styles.icon}
          accessibilityElementsHidden
          importantForAccessibility="no-hide-descendants"
        >
          🖥️
        </Text>
        <Text style={styles.title}>Compte administrateur</Text>
        <Text style={styles.message}>
          L&apos;interface d&apos;administration est disponible uniquement sur le web.
          Connectez-vous depuis un ordinateur pour gérer les comptes et la supervision technique de
          MedLink.
        </Text>

        <TouchableOpacity
          style={styles.button}
          onPress={() => confirmLogout(logout)}
          accessibilityRole="button"
          accessibilityLabel="Se déconnecter"
        >
          <Text style={styles.buttonText}>Se déconnecter</Text>
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.background },
  content: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 32, gap: 16 },
  icon: { fontSize: 48 },
  title: { fontSize: TYPE.xl, fontWeight: '700', color: COLORS.primary, textAlign: 'center' },
  message: {
    fontSize: TYPE.base,
    color: COLORS.mutedText,
    textAlign: 'center',
    lineHeight: 22,
  },
  button: {
    marginTop: 8,
    minHeight: 48,
    minWidth: 200,
    justifyContent: 'center',
    alignItems: 'center',
    borderRadius: 33,
    backgroundColor: COLORS.primary,
    paddingHorizontal: 24,
  },
  buttonText: { color: COLORS.onPrimary, fontWeight: '700', fontSize: TYPE.sm },
});
