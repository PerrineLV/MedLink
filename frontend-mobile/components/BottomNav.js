import { Alert, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { COLORS, TYPE } from '../services/journalPresentation';

const MIN_TOUCH_TARGET = 44;

const COMING_SOON_TITLE = 'Bientôt disponible';
const COMING_SOON_MESSAGE = 'Cette fonctionnalité arrive dans une prochaine version de MedLink.';

function notifyComingSoon() {
  Alert.alert(COMING_SOON_TITLE, COMING_SOON_MESSAGE);
}

function confirmLogout(logout) {
  Alert.alert('Profil', 'Voulez-vous vous déconnecter ?', [
    { text: 'Annuler', style: 'cancel' },
    { text: 'Se déconnecter', style: 'destructive', onPress: logout },
  ]);
}

// L'onglet "Profil" n'a pas d'écran dédié : la barre de navigation étant
// figée à 5 items (cf. skill medlink-ui-conventions), "Mes liaisons" (ML-47)
// s'y ajoute comme option de menu plutôt que comme 6e item.
export function openProfileMenu(navigation, logout) {
  Alert.alert('Profil', undefined, [
    { text: 'Mes liaisons', onPress: () => navigation.navigate('Liaisons') },
    { text: 'Se déconnecter', style: 'destructive', onPress: () => confirmLogout(logout) },
    { text: 'Annuler', style: 'cancel' },
  ]);
}

const BOTTOM_NAV_ITEMS = [
  { key: 'Journal', icon: '📓', screen: 'Journal' },
  { key: 'Messages', icon: '💬', screen: null },
  { key: 'RDV', icon: '📅', screen: null },
  { key: 'Export', icon: '📤', screen: null },
  { key: 'Profil', icon: '👤', screen: null },
];

export default function BottomNav({ navigation, activeKey, onProfilePress }) {
  return (
    <View style={styles.bottomNav}>
      {BOTTOM_NAV_ITEMS.map((item) => {
        const isActive = item.key === activeKey;

        const onPress = () => {
          if (item.key === 'Profil') return onProfilePress();
          if (item.screen) return navigation.navigate(item.screen);

          return notifyComingSoon();
        };

        return (
          <TouchableOpacity
            key={item.key}
            style={styles.bottomNavItem}
            onPress={onPress}
            accessibilityRole="button"
            accessibilityState={{ selected: isActive }}
            accessibilityLabel={item.key}
          >
            <Text
              style={styles.bottomNavIcon}
              accessibilityElementsHidden
              importantForAccessibility="no-hide-descendants"
            >
              {item.icon}
            </Text>
            <Text style={[styles.bottomNavLabel, isActive && styles.bottomNavLabelActive]}>{item.key}</Text>
          </TouchableOpacity>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  bottomNav: {
    flexDirection: 'row',
    backgroundColor: COLORS.primary,
    paddingTop: 8,
    paddingBottom: 20,
  },
  bottomNavItem: {
    flex: 1,
    minHeight: MIN_TOUCH_TARGET,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 2,
  },
  bottomNavIcon: { fontSize: TYPE.md },
  bottomNavLabel: { fontSize: TYPE.xs, color: 'rgba(255,255,255,0.7)' },
  bottomNavLabelActive: { color: COLORS.onPrimary, fontWeight: '700' },
});
