import { useCallback, useState } from 'react';
import { useFocusEffect } from '@react-navigation/native';
import { Alert, Modal, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
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

// Menu "Profil" (ML-61) : l'onglet n'a pas d'écran dédié, la barre de
// navigation étant figée à 5 items (cf. skill medlink-ui-conventions).
// Modale maison (au lieu d'un Alert.alert natif) pour reprendre l'identité
// visuelle MedLink — même habillage que la modale de confirmation de
// suppression de compte (AccountScreen).
function ProfileMenuModal({ visible, onClose, navigation, roles, logout }) {
  const canReceiveInvitations = roles.includes(ROLE_AIDANT) || roles.includes(ROLE_SOIGNANT);
  // "Mes liaisons" (ML-47) est réservé au patient (c'est lui qui gère ses
  // propres liens) ; l'aidant et le soignant y voient à la place
  // "Invitations reçues" (ML-48), l'écran symétrique côté destinataire.
  const linkItem = canReceiveInvitations
    ? { key: 'invitations', label: 'Invitations reçues', screen: 'Invitations' }
    : { key: 'liaisons', label: 'Mes liaisons', screen: 'Liaisons' };

  const navigateTo = (screen) => {
    onClose();
    navigation.navigate(screen);
  };

  const handleLogoutPress = () => {
    onClose();
    confirmLogout(logout);
  };

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <View style={styles.menuOverlay}>
        <View style={styles.menuCard} accessibilityRole="alert" accessibilityViewIsModal>
          <Text style={styles.menuTitle} accessibilityRole="header">
            Profil
          </Text>

          <TouchableOpacity
            style={styles.menuItem}
            onPress={() => navigateTo(linkItem.screen)}
            accessibilityRole="button"
            accessibilityLabel={linkItem.label}
          >
            <Text style={styles.menuItemText}>{linkItem.label}</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.menuItem}
            onPress={() => navigateTo('Account')}
            accessibilityRole="button"
            accessibilityLabel="Mon compte"
          >
            <Text style={styles.menuItemText}>Mon compte</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.menuLogoutButton}
            onPress={handleLogoutPress}
            accessibilityRole="button"
            accessibilityLabel="Se déconnecter"
          >
            <Text style={styles.menuLogoutText}>Se déconnecter</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.menuCancelButton}
            onPress={onClose}
            accessibilityRole="button"
            accessibilityLabel="Annuler"
          >
            <Text style={styles.menuCancelText}>Annuler</Text>
          </TouchableOpacity>
        </View>
      </View>
    </Modal>
  );
}

const BOTTOM_NAV_ITEMS = [
  { key: 'Journal', icon: '📓', screen: 'Journal' },
  { key: 'Messages', icon: '💬', screen: 'Messages' },
  { key: 'RDV', icon: '📅', screen: 'Appointments' },
  { key: 'Export', icon: '📤', screen: 'Export' },
  { key: 'Profil', icon: '👤', screen: null },
];

export default function BottomNav({ navigation, activeKey, logout, roles = [] }) {
  const [isProfileMenuVisible, setIsProfileMenuVisible] = useState(false);
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
          if (item.key === 'Profil') return setIsProfileMenuVisible(true);
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

      <ProfileMenuModal
        visible={isProfileMenuVisible}
        onClose={() => setIsProfileMenuVisible(false)}
        navigation={navigation}
        roles={roles}
        logout={logout}
      />
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
  menuOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.4)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  menuCard: {
    backgroundColor: COLORS.primary,
    borderRadius: 16,
    padding: 24,
    width: '100%',
    maxWidth: 360,
    gap: 12,
  },
  menuTitle: {
    color: COLORS.onPrimary,
    fontSize: TYPE.md,
    fontWeight: '700',
    marginBottom: 4,
  },
  menuItem: {
    minHeight: MIN_TOUCH_TARGET,
    justifyContent: 'center',
    alignItems: 'center',
    borderRadius: 33,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.5)',
    paddingVertical: 8,
    paddingHorizontal: 16,
  },
  menuItemText: { color: COLORS.onPrimary, fontWeight: '600', fontSize: TYPE.sm },
  menuLogoutButton: {
    minHeight: MIN_TOUCH_TARGET,
    justifyContent: 'center',
    alignItems: 'center',
    borderRadius: 33,
    backgroundColor: COLORS.onPrimary,
    paddingVertical: 8,
    paddingHorizontal: 16,
  },
  menuLogoutText: { color: COLORS.red.text, fontWeight: '700', fontSize: TYPE.sm },
  menuCancelButton: {
    minHeight: MIN_TOUCH_TARGET,
    justifyContent: 'center',
    alignItems: 'center',
    borderRadius: 33,
    paddingVertical: 8,
    paddingHorizontal: 16,
  },
  menuCancelText: { color: 'rgba(255,255,255,0.7)', fontWeight: '600', fontSize: TYPE.sm },
});
