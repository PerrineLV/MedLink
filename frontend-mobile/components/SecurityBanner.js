import { StyleSheet, Text, View } from 'react-native';
import { COLORS, TYPE } from '../services/journalPresentation';

export default function SecurityBanner() {
  return (
    <View style={styles.securityBanner}>
      <Text style={styles.securityBannerText}>Données chiffrées - accès soignants uniquement</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  securityBanner: {
    backgroundColor: COLORS.mutedBackground,
    paddingVertical: 10,
    paddingHorizontal: 20,
  },
  securityBannerText: { color: COLORS.primary, fontSize: TYPE.xs, fontWeight: '600' },
});
