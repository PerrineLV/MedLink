import { useEffect, useState } from 'react';
import { Linking, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import Constants from 'expo-constants';
import httpClient from '../services/httpClient';

const CHECK_TIMEOUT_MS = 2000;

function isRemoteVersionNewer(remoteVersion, installedVersion) {
  const remoteParts = String(remoteVersion).split('.').map(Number);
  const installedParts = String(installedVersion).split('.').map(Number);
  const length = Math.max(remoteParts.length, installedParts.length);

  for (let i = 0; i < length; i += 1) {
    const remotePart = remoteParts[i] ?? 0;
    const installedPart = installedParts[i] ?? 0;
    if (remotePart !== installedPart) {
      return remotePart > installedPart;
    }
  }
  return false;
}

// Update-checker (ML-98) : pas de mise à jour silencieuse (hors périmètre),
// juste une bannière non bloquante qui pointe vers l'APK à jour. L'appel
// réseau ne doit jamais impacter le démarrage perçu : timeout court et échec
// systématiquement silencieux (pas de retry, pas d'erreur visible).
export default function UpdateBanner() {
  const [updateInfo, setUpdateInfo] = useState(null);
  const [dismissed, setDismissed] = useState(false);

  useEffect(() => {
    let isMounted = true;

    httpClient
      .get('/app-version', { timeout: CHECK_TIMEOUT_MS })
      .then(({ data }) => {
        const installedVersion = Constants.expoConfig?.version;
        if (isMounted && installedVersion && isRemoteVersionNewer(data.version, installedVersion)) {
          setUpdateInfo(data);
        }
      })
      .catch(() => {});

    return () => {
      isMounted = false;
    };
  }, []);

  if (!updateInfo || dismissed) {
    return null;
  }

  return (
    <View
      style={styles.banner}
      accessibilityRole="text"
      accessibilityLabel={`Nouvelle version disponible : ${updateInfo.version}`}
    >
      <Text style={styles.text}>Nouvelle version disponible</Text>
      <View style={styles.actions}>
        <TouchableOpacity
          style={styles.actionButton}
          onPress={() => Linking.openURL(updateInfo.apk_url)}
          accessibilityRole="button"
          accessibilityLabel="Télécharger la nouvelle version"
        >
          <Text style={styles.actionText}>Télécharger</Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={styles.actionButton}
          onPress={() => setDismissed(true)}
          accessibilityRole="button"
          accessibilityLabel="Ignorer la notification de mise à jour"
        >
          <Text style={styles.actionText}>Ignorer</Text>
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  banner: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    zIndex: 1000,
    backgroundColor: '#2E3862',
    paddingTop: 48,
    paddingBottom: 12,
    paddingHorizontal: 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  text: { color: '#fff', fontSize: 14, flexShrink: 1 },
  actions: { flexDirection: 'row' },
  actionButton: {
    minHeight: 44,
    minWidth: 44,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 8,
  },
  actionText: { color: '#fff', fontWeight: '600', fontSize: 13, textDecorationLine: 'underline' },
});
