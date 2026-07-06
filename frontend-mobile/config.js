import { Platform } from 'react-native';

// Sur un téléphone physique (Expo Go via LAN), ni "localhost" ni l'alias
// Android 10.0.2.2 ne joignent la machine hôte : il faut l'IP LAN explicite,
// fournie par EXPO_PUBLIC_API_URL (voir docker-compose.yml / HOST_LAN_IP).
const API_BASE_URL =
  process.env.EXPO_PUBLIC_API_URL ??
  Platform.select({
    // L'émulateur Android ne peut pas joindre localhost de la machine hôte,
    // il faut passer par l'alias spécial 10.0.2.2.
    android: 'http://10.0.2.2:8080/api',
    default: 'http://localhost:8080/api',
  });

export default API_BASE_URL;
