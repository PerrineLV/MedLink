import { ScrollView, StyleSheet, Text, TouchableOpacity } from 'react-native';

const COLORS = {
  primary: '#2E3862',
  primaryLight: '#3B5BDB',
  surface: '#FFFFFF',
  text: '#1C2338',
  textMuted: '#5B6178',
};

export default function PrivacyPolicyScreen({ navigation }) {
  return (
    <ScrollView contentContainerStyle={styles.container}>
      <TouchableOpacity
        onPress={() => navigation.goBack()}
        accessibilityRole="button"
        accessibilityLabel="Retour"
        style={styles.backButton}
      >
        <Text style={styles.backText}>‹ Retour</Text>
      </TouchableOpacity>

      <Text style={styles.title}>MedLink</Text>
      <Text style={styles.subtitle}>Politique de confidentialité</Text>

      <Text style={styles.heading}>Données traitées</Text>
      <Text style={styles.paragraph}>
        Dans le cadre de votre suivi médical à domicile, MedLink traite des données de santé vous
        concernant : entrées de votre journal de suivi (humeur, douleur, tension, notes libres),
        informations relatives à vos traitements, ainsi que les messages échangés avec les aidants
        et professionnels de santé qui vous accompagnent.
      </Text>

      <Text style={styles.heading}>Finalité</Text>
      <Text style={styles.paragraph}>
        Ces données sont utilisées exclusivement pour assurer la coordination de votre suivi médical
        entre vous, vos aidants et les professionnels de santé rattachés à votre dossier.
      </Text>

      <Text style={styles.heading}>Base légale</Text>
      <Text style={styles.paragraph}>
        Le traitement repose sur votre consentement explicite, recueilli lors de votre inscription
        (article 6.1.a du RGPD). S’agissant de données de santé, ce consentement explicite constitue
        également la base légale requise par l’article 9.2.a du RGPD pour le traitement de données
        dites « sensibles ».
      </Text>

      <Text style={styles.heading}>Durée de conservation</Text>
      <Text style={styles.paragraph}>
        Vos données sont conservées pendant toute la durée d’utilisation de votre compte. En cas de
        clôture du compte, elles sont supprimées dans un délai de 3 ans, sauf obligation légale de
        conservation plus longue.
      </Text>

      <Text style={styles.heading}>Vos droits</Text>
      <Text style={styles.paragraph}>
        Conformément au RGPD, vous disposez d’un droit d’accès, de rectification, de portabilité et
        d’effacement de vos données. Vous pouvez exercer ces droits directement depuis l’écran « Mon
        compte » une fois connecté·e.
      </Text>

      <Text style={styles.heading}>Hébergement</Text>
      <Text style={styles.paragraph}>
        Vos données sont hébergées par OVHcloud (offre VPS-1), sur des serveurs situés en France,
        avec un SLA de disponibilité contractuel de 99,9 %.
      </Text>

      <Text style={styles.heading}>Contact</Text>
      <Text style={styles.paragraph}>
        Pour toute question relative à vos données personnelles, vous pouvez nous contacter à
        l’adresse suivante : dpo@medlink.test.
      </Text>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flexGrow: 1, padding: 24, backgroundColor: COLORS.surface },
  backButton: {
    marginBottom: 16,
    alignSelf: 'flex-start',
    minHeight: 44,
    justifyContent: 'center',
  },
  backText: { color: COLORS.primaryLight, fontWeight: '600', fontSize: 16 },
  title: { fontSize: 28, fontWeight: '700', color: COLORS.primary, marginBottom: 4 },
  subtitle: { fontSize: 16, color: COLORS.textMuted, marginBottom: 32 },
  heading: {
    fontSize: 16,
    fontWeight: '700',
    color: COLORS.primary,
    marginTop: 24,
    marginBottom: 6,
  },
  paragraph: { fontSize: 14, color: COLORS.text, lineHeight: 20 },
});
