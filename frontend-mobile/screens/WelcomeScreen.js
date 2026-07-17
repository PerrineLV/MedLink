import { Image, ScrollView, StyleSheet, Text, TouchableOpacity, View } from 'react-native';

const HIGHLIGHTS = [
  'Centraliser vos échanges médicaux',
  'Coordination entre patient, aidant et soignant',
  'Suivi sécurisé et accessible à tout moment',
];

const COLORS = {
  primary: '#2E3862',
  primaryLight: '#3B5BDB',
  surface: '#FFFFFF',
  tagline: '#A9B8DE',
};

// Logo et illustration (ML-64) : recadrés depuis la maquette Figma et le
// logo fournis directement par Perrine (quota Figma MCP épuisé, cf.
// commentaire ML-64) — mêmes fichiers que web/src/assets.
const logo = require('../assets/medlink-logo.png');
const illustration = require('../assets/welcome-illustration.png');

export default function WelcomeScreen({ navigation }) {
  return (
    <View style={styles.screen}>
      <Image
        source={illustration}
        style={styles.illustration}
        resizeMode="contain"
        accessibilityRole="image"
        accessibilityLabel="Illustration d'une soignante et d'un soignant souriants, prêts à accompagner le suivi médical partagé sur MedLink"
      />

      <ScrollView contentContainerStyle={styles.container}>
        <View style={styles.logoBlock}>
          <Image source={logo} style={styles.logoImage} resizeMode="contain" />
          <Text style={styles.logoWordmark}>MedLink</Text>
          <Text style={styles.logoTagline}>Lien Médical Simplifié</Text>
        </View>

        <Text style={styles.title}>Vous découvrez MedLink ?</Text>

        <View style={styles.highlights}>
          {HIGHLIGHTS.map((label) => (
            <View key={label} style={styles.highlightRow}>
              <Text style={styles.highlightIcon} accessibilityElementsHidden>
                🩺
              </Text>
              <Text style={styles.highlightLabel}>{label}</Text>
            </View>
          ))}
        </View>

        <View style={styles.actions}>
          <TouchableOpacity
            style={styles.buttonPrimary}
            onPress={() => navigation.navigate('Register')}
            accessibilityRole="button"
            accessibilityLabel="Inscription"
          >
            <Text style={styles.buttonPrimaryText}>Inscription</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.buttonSecondary}
            onPress={() => navigation.navigate('Login')}
            accessibilityRole="button"
            accessibilityLabel="Connexion"
          >
            <Text style={styles.buttonSecondaryText}>Connexion</Text>
          </TouchableOpacity>
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.primary, position: 'relative' },
  container: {
    flexGrow: 1,
    alignItems: 'center',
    padding: 24,
    paddingTop: 48,
    paddingBottom: 160,
    gap: 28,
  },
  logoBlock: { alignItems: 'center' },
  logoImage: { width: 72, height: 72 },
  logoWordmark: { color: COLORS.surface, fontSize: 22, fontWeight: '800', marginTop: 6 },
  logoTagline: { color: COLORS.tagline, fontSize: 12, marginTop: 2 },
  title: {
    width: '100%',
    color: COLORS.surface,
    fontSize: 19,
    fontWeight: '700',
  },
  highlights: { width: '100%', gap: 16 },
  highlightRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  // Même icône (stéthoscope) que le web : pas de bibliothèque d'icônes
  // possible en React Native (cf. skill medlink-ui-conventions), emoji à la
  // place — 🩺 est un vrai caractère Unicode (Emoji 12.0), pas un pictogramme
  // approximatif.
  highlightIcon: { fontSize: 18 },
  highlightLabel: { flex: 1, fontSize: 14, fontWeight: '500', color: COLORS.surface },
  actions: { width: '100%', gap: 14 },
  buttonPrimary: {
    width: '100%',
    minHeight: 44,
    paddingVertical: 12,
    borderRadius: 33,
    backgroundColor: COLORS.surface,
    alignItems: 'center',
    justifyContent: 'center',
  },
  buttonPrimaryText: { color: COLORS.primary, fontSize: 15, fontWeight: '700' },
  buttonSecondary: {
    width: '100%',
    minHeight: 44,
    paddingVertical: 12,
    borderRadius: 33,
    backgroundColor: COLORS.primaryLight,
    alignItems: 'center',
    justifyContent: 'center',
  },
  buttonSecondaryText: { color: COLORS.surface, fontSize: 15, fontWeight: '700' },
  illustration: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    width: 220,
    height: 190,
  },
});
