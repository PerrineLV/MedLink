import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { login as loginRequest } from '../services/authService';
import httpClient from '../services/httpClient';
import { decodeJwtPayload } from '../services/jwt';
import { AuthContext } from './useAuth';

// 30 min of inactivity logs the user out; a warning appears 2 min before.
const INACTIVITY_LOGOUT_MS = 30 * 60 * 1000;
const INACTIVITY_WARNING_MS = INACTIVITY_LOGOUT_MS - 2 * 60 * 1000;

const ACTIVITY_EVENTS = ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'];

// sessionStorage (not localStorage) so the token doesn't outlive the browser tab.
const TOKEN_STORAGE_KEY = 'medlink_token';

// Reads a still-valid token back from sessionStorage on mount, so a page
// reload doesn't force a re-login while the JWT itself is still valid.
function readStoredSession() {
  let storedToken;
  try {
    storedToken = sessionStorage.getItem(TOKEN_STORAGE_KEY);
  } catch {
    return null;
  }

  if (!storedToken) {
    return null;
  }

  const payload = decodeJwtPayload(storedToken);
  if (!payload?.exp || payload.exp * 1000 <= Date.now()) {
    sessionStorage.removeItem(TOKEN_STORAGE_KEY);
    return null;
  }

  return {
    token: storedToken,
    roles: payload.roles ?? [],
    firstName: payload.firstName ?? null,
  };
}

export function AuthProvider({ children }) {
  // Set eagerly during the initial render (not in an effect) so the header
  // is already in place before any child's mount effect fires its first
  // API call — child effects run before this component's own effects, so
  // an effect-based assignment here would race and lose that first call.
  const [token, setToken] = useState(() => {
    const stored = readStoredSession();
    if (stored?.token) {
      httpClient.defaults.headers.common.Authorization = `Bearer ${stored.token}`;
    }
    return stored?.token ?? null;
  });
  const [roles, setRoles] = useState(() => readStoredSession()?.roles ?? []);
  const [firstName, setFirstName] = useState(() => readStoredSession()?.firstName ?? null);
  const [sessionExpiryWarning, setSessionExpiryWarning] = useState(false);

  const warningTimeoutRef = useRef(null);
  const logoutTimeoutRef = useRef(null);

  const clearInactivityTimers = useCallback(() => {
    clearTimeout(warningTimeoutRef.current);
    clearTimeout(logoutTimeoutRef.current);
  }, []);

  const logout = useCallback(() => {
    clearInactivityTimers();
    try {
      sessionStorage.removeItem(TOKEN_STORAGE_KEY);
    } catch {
      // ignore — storage may be unavailable (private browsing, quota...)
    }
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

    try {
      sessionStorage.setItem(TOKEN_STORAGE_KEY, accessToken);
    } catch {
      // ignore — storage may be unavailable (private browsing, quota...)
    }

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
