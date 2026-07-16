import { StyleSheet, Text, View } from 'react-native';
import SecurityBanner from './SecurityBanner';
import { COLORS, TYPE } from '../services/journalPresentation';

// Le bandeau de sécurité (ML-92) fait partie intégrante de l'en-tête, pas une
// option que chaque écran doit penser à ajouter : un écran qui l'omettait
// causait un décalage de layout (le contenu "remonte") à la navigation
// vers/depuis cet écran.
export default function Header({ displayName }) {
  return (
    <>
      <View style={styles.header}>
        <View style={styles.headerBrand}>
          <Text
            style={styles.headerLogo}
            accessibilityElementsHidden
            importantForAccessibility="no-hide-descendants"
          >
            🛡️
          </Text>
          <View>
            <Text style={styles.headerTitle}>MedLink</Text>
            <Text style={styles.headerPatientName}>{displayName}</Text>
          </View>
        </View>

        <View accessible accessibilityRole="text" accessibilityLabel="Connexion sécurisée">
          <Text
            style={styles.headerLock}
            accessibilityElementsHidden
            importantForAccessibility="no-hide-descendants"
          >
            🔒
          </Text>
        </View>
      </View>

      <SecurityBanner />
    </>
  );
}

const styles = StyleSheet.create({
  header: {
    backgroundColor: COLORS.primary,
    paddingTop: 56,
    paddingBottom: 16,
    paddingHorizontal: 20,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  headerBrand: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  headerLogo: { fontSize: TYPE.lg },
  headerTitle: { color: COLORS.onPrimary, fontSize: TYPE.md, fontWeight: '700' },
  headerPatientName: { color: COLORS.onPrimary, fontSize: TYPE.sm, opacity: 0.85 },
  headerLock: { fontSize: TYPE.lg },
});
