import { StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { useAuth } from '../contexts/AuthContext';
import { ROLE_LABELS, getPrimaryRole } from '../services/roles';

export default function DashboardScreen() {
  const { roles, logout } = useAuth();
  const primaryRole = getPrimaryRole(roles);
  const roleLabel = primaryRole ? ROLE_LABELS[primaryRole] : 'Utilisateur';

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.title}>Bienvenue</Text>
        <TouchableOpacity
          style={styles.logoutButton}
          onPress={logout}
          accessibilityRole="button"
          accessibilityLabel="Se déconnecter"
        >
          <Text style={styles.logoutText}>Se déconnecter</Text>
        </TouchableOpacity>
      </View>
      <Text style={styles.text}>
        Vous êtes connecté·e en tant que <Text style={styles.bold}>{roleLabel}</Text>.
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#F4F6FB', padding: 24, paddingTop: 60 },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 24,
  },
  title: { fontSize: 28, fontWeight: '700', color: '#2E3862' },
  logoutButton: {
    backgroundColor: '#2E3862',
    borderRadius: 33,
    paddingVertical: 10,
    paddingHorizontal: 20,
  },
  logoutText: { color: '#fff', fontWeight: '600' },
  text: { fontSize: 16, color: '#1C2338' },
  bold: { fontWeight: '700' },
});
