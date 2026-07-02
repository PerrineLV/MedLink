import { Platform } from 'react-native';

// L'émulateur Android ne peut pas joindre localhost de la machine hôte,
// il faut passer par l'alias spécial 10.0.2.2.
const API_BASE_URL = Platform.select({
  android: 'http://10.0.2.2:8080/api',
  default: 'http://localhost:8080/api',
});

export default API_BASE_URL;
