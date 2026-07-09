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
import httpClient from '../services/httpClient';
import { decodeJwtPayload } from '../services/jwt';

// 30 min of inactivity logs the user out; a warning appears 2 min before.
const INACTIVITY_LOGOUT_MS = 30 * 60 * 1000;
const INACTIVITY_WARNING_MS = INACTIVITY_LOGOUT_MS - 2 * 60 * 1000;

const ACTIVITY_EVENTS = ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'];

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

    const handleActivity = () => resetInactivityTimers();
    ACTIVITY_EVENTS.forEach((eventName) => window.addEventListener(eventName, handleActivity));

    return () => {
      ACTIVITY_EVENTS.forEach((eventName) => window.removeEventListener(eventName, handleActivity));
      clearInactivityTimers();
    };
    // resetInactivityTimers/clearInactivityTimers are stable (useCallback with stable deps).
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [token]);

  useEffect(() => {
    if (token) {
      httpClient.defaults.headers.common.Authorization = `Bearer ${token}`;
    } else {
      delete httpClient.defaults.headers.common.Authorization;
    }
  }, [token]);

  const login = useCallback(async (email, password) => {
    const { token: accessToken } = await loginRequest(email, password);
    const payload = decodeJwtPayload(accessToken);
    const grantedRoles = payload?.roles ?? [];

    setToken(accessToken);
    setRoles(grantedRoles);
    setFirstName(payload?.firstName ?? null);

    return grantedRoles;
  }, []);

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
    }),
    [token, roles, firstName, sessionExpiryWarning, login, logout, resetInactivityTimers],
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
