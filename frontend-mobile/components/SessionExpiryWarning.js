import { Modal, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { useAuth } from '../contexts/AuthContext';

export default function SessionExpiryWarning() {
  const { sessionExpiryWarning, dismissSessionExpiryWarning, logout } = useAuth();

  return (
    <Modal visible={sessionExpiryWarning} transparent animationType="fade">
      <View style={styles.overlay}>
        <View style={styles.card} accessibilityRole="alert">
          <Text style={styles.text}>Votre session va expirer dans 2 minutes par inactivité.</Text>
          <View style={styles.actions}>
            <TouchableOpacity
              style={styles.stayButton}
              onPress={dismissSessionExpiryWarning}
              accessibilityRole="button"
              accessibilityLabel="Rester connecté"
            >
              <Text style={styles.stayText}>Rester connecté·e</Text>
            </TouchableOpacity>
            <TouchableOpacity
              style={styles.logoutButton}
              onPress={logout}
              accessibilityRole="button"
              accessibilityLabel="Se déconnecter"
            >
              <Text style={styles.logoutText}>Se déconnecter</Text>
            </TouchableOpacity>
          </View>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.4)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  card: {
    backgroundColor: '#2E3862',
    borderRadius: 16,
    padding: 24,
    width: '100%',
    maxWidth: 360,
  },
  text: { color: '#fff', fontSize: 15, marginBottom: 16 },
  actions: { flexDirection: 'row', gap: 12, justifyContent: 'flex-end' },
  stayButton: {
    backgroundColor: '#fff',
    borderRadius: 33,
    paddingVertical: 8,
    paddingHorizontal: 16,
  },
  stayText: { color: '#2E3862', fontWeight: '600' },
  logoutButton: {
    borderRadius: 33,
    paddingVertical: 8,
    paddingHorizontal: 16,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.5)',
  },
  logoutText: { color: '#fff', fontWeight: '600' },
});
