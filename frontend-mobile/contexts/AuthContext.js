import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
} from 'react';
import { login as loginRequest } from '../services/authService';
import { setAuthToken } from '../services/httpClient';
import { decodeJwtPayload } from '../services/jwt';

// 30 min of inactivity logs the user out; a warning appears 2 min before.
const INACTIVITY_LOGOUT_MS = 30 * 60 * 1000;
const INACTIVITY_WARNING_MS = INACTIVITY_LOGOUT_MS - 2 * 60 * 1000;

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [token, setToken] = useState(null);
  const [roles, setRoles] = useState([]);
  const [firstName, setFirstName] = useState(null);
  const [sessionExpiryWarning, setSessionExpiryWarning] = useState(false);

  const warningTimeoutRef = useRef(null);
  const logoutTimeoutRef = useRef(null);

  const clearInactivityTimers = useCallback(() => {
    clearTimeout(warningTimeoutRef.current);
    clearTimeout(logoutTimeoutRef.current);
  }, []);

  const logout = useCallback(() => {
    clearInactivityTimers();
    setAuthToken(null);
    setToken(null);
    setRoles([]);
    setFirstName(null);
    setSessionExpiryWarning(false);
  }, [clearInactivityTimers]);

  const resetInactivityTimers = useCallback(() => {
    clearInactivityTimers();
    setSessionExpiryWarning(false);
    warningTimeoutRef.current = setTimeout(() => {
      setSessionExpiryWarning(true);
    }, INACTIVITY_WARNING_MS);
    logoutTimeoutRef.current = setTimeout(logout, INACTIVITY_LOGOUT_MS);
  }, [clearInactivityTimers, logout]);

  useEffect(() => {
    if (!token) {
      clearInactivityTimers();
      return;
    }

    resetInactivityTimers();

    return clearInactivityTimers;
    // resetInactivityTimers/clearInactivityTimers are stable (useCallback with stable deps).
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [token]);

  const login = useCallback(async (email, password) => {
    const { token: accessToken } = await loginRequest(email, password);
    const payload = decodeJwtPayload(accessToken);
    const grantedRoles = payload?.roles ?? [];

    // Set synchronously, ahead of setToken: httpClient reads this on every
    // request, so it must be current before JournalScreen's first fetch
    // fires in the same commit that mounts it (see ML-100).
    setAuthToken(accessToken);
    setToken(accessToken);
    setRoles(grantedRoles);
    setFirstName(payload?.firstName ?? null);

    return grantedRoles;
  }, []);

  // There's no global DOM to attach activity listeners to on native — the
  // app root wraps everything in a touch-capturing View that calls this on
  // every tap (see App.js).
  const registerActivity = useCallback(() => {
    if (token) {
      resetInactivityTimers();
    }
  }, [token, resetInactivityTimers]);

  const value = useMemo(
    () => ({
      token,
      roles,
      firstName,
      isAuthenticated: Boolean(token),
      sessionExpiryWarning,
      login,
      logout,
      dismissSessionExpiryWarning: resetInactivityTimers,
      registerActivity,
    }),
    [
      token,
      roles,
      firstName,
      sessionExpiryWarning,
      login,
      logout,
      resetInactivityTimers,
      registerActivity,
    ],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }

  return context;
}
