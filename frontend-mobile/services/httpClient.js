import axios from 'axios';
import API_BASE_URL from '../config';

const httpClient = axios.create({
  baseURL: API_BASE_URL,
  // Plain JSON instead of API Platform's default JSON-LD: no @context/@id
  // noise to strip out on a small mobile client.
  headers: { Accept: 'application/json' },
});

// Mutable holder read synchronously by the interceptor below, updated by
// AuthContext at the same time as setToken (not via a useEffect) so the
// header is never missing on the first request after login — see ML-100.
let authToken = null;

export function setAuthToken(token) {
  authToken = token;
}

httpClient.interceptors.request.use((config) => {
  if (authToken) {
    config.headers.Authorization = `Bearer ${authToken}`;
  } else {
    delete config.headers.Authorization;
  }
  return config;
});

export default httpClient;
