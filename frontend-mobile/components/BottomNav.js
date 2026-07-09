import { useCallback } from 'react';
import { useFocusEffect } from '@react-navigation/native';
import { Alert, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { useInvitationsBadge } from '../contexts/InvitationsBadgeContext';
import { useMessagesBadge } from '../contexts/MessagesBadgeContext';
import { COLORS, TYPE } from '../services/journalPresentation';
import { ROLE_AIDANT, ROLE_SOIGNANT } from '../services/roles';

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
// figée à 5 items (cf. skill medlink-ui-conventions). "Mes liaisons" (ML-47)
// est réservé au patient (c'est lui qui gère ses propres liens) ; l'aidant et
// le soignant y voient à la place "Invitations reçues" (ML-48), l'écran
// symétrique côté destinataire.
export function openProfileMenu(navigation, logout, roles = []) {
  const canReceiveInvitations = roles.includes(ROLE_AIDANT) || roles.includes(ROLE_SOIGNANT);

  const menuItem = canReceiveInvitations
    ? { text: 'Invitations reçues', onPress: () => navigation.navigate('Invitations') }
    : { text: 'Mes liaisons', onPress: () => navigation.navigate('Liaisons') };

  Alert.alert('Profil', undefined, [
    menuItem,
    { text: 'Se déconnecter', style: 'destructive', onPress: () => confirmLogout(logout) },
    { text: 'Annuler', style: 'cancel' },
  ]);
}

const BOTTOM_NAV_ITEMS = [
  { key: 'Journal', icon: '📓', screen: 'Journal' },
  { key: 'Messages', icon: '💬', screen: 'Messages' },
  { key: 'RDV', icon: '📅', screen: null },
  { key: 'Export', icon: '📤', screen: null },
  { key: 'Profil', icon: '👤', screen: null },
];

export default function BottomNav({ navigation, activeKey, onProfilePress, roles = [] }) {
  const canReceiveInvitations = roles.includes(ROLE_AIDANT) || roles.includes(ROLE_SOIGNANT);
  const { pendingInvitationsCount, refresh: refreshPendingInvitationsCount } =
    useInvitationsBadge();
  const { unreadMessagesCount, refresh: refreshUnreadMessagesCount } = useMessagesBadge();

  useFocusEffect(
    useCallback(() => {
      if (canReceiveInvitations) refreshPendingInvitationsCount();
      refreshUnreadMessagesCount();
    }, [canReceiveInvitations, refreshPendingInvitationsCount, refreshUnreadMessagesCount]),
  );

  return (
    <View style={styles.bottomNav}>
      {BOTTOM_NAV_ITEMS.map((item) => {
        const isActive = item.key === activeKey;
        const isProfil = item.key === 'Profil';
        const isMessages = item.key === 'Messages';
        const badgeCount =
          isProfil && canReceiveInvitations
            ? pendingInvitationsCount
            : isMessages
              ? unreadMessagesCount
              : 0;
        const showBadge = badgeCount > 0;

        const onPress = () => {
          if (item.key === 'Profil') return onProfilePress();
          if (item.screen) return navigation.navigate(item.screen);

          return notifyComingSoon();
        };

        const badgeAccessibilityLabel = isProfil
          ? `${badgeCount} invitation${badgeCount > 1 ? 's' : ''} en attente`
          : `${badgeCount} message${badgeCount > 1 ? 's' : ''} non lu${badgeCount > 1 ? 's' : ''}`;

        return (
          <TouchableOpacity
            key={item.key}
            style={styles.bottomNavItem}
            onPress={onPress}
            accessibilityRole="button"
            accessibilityState={{ selected: isActive }}
            accessibilityLabel={showBadge ? `${item.key}, ${badgeAccessibilityLabel}` : item.key}
          >
            <View style={styles.bottomNavIconWrapper}>
              <Text
                style={styles.bottomNavIcon}
                accessibilityElementsHidden
                importantForAccessibility="no-hide-descendants"
              >
                {item.icon}
              </Text>
              {showBadge && (
                <View
                  style={styles.bottomNavBadge}
                  accessibilityElementsHidden
                  importantForAccessibility="no-hide-descendants"
                >
                  <Text style={styles.bottomNavBadgeText}>{badgeCount}</Text>
                </View>
              )}
            </View>
            <Text style={[styles.bottomNavLabel, isActive && styles.bottomNavLabelActive]}>
              {item.key}
            </Text>
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
  bottomNavIconWrapper: { position: 'relative' },
  bottomNavIcon: { fontSize: TYPE.md },
  bottomNavBadge: {
    position: 'absolute',
    top: -4,
    right: -8,
    minWidth: 16,
    height: 16,
    borderRadius: 8,
    paddingHorizontal: 3,
    backgroundColor: COLORS.red.text,
    alignItems: 'center',
    justifyContent: 'center',
  },
  bottomNavBadgeText: { color: COLORS.onPrimary, fontSize: 10, fontWeight: '700' },
  bottomNavLabel: { fontSize: TYPE.xs, color: 'rgba(255,255,255,0.7)' },
  bottomNavLabelActive: { color: COLORS.onPrimary, fontWeight: '700' },
});
