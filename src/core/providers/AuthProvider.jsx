import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import api from '../api';

const AuthContext = createContext(null);

/**
 * Authentification (B1 v2).
 * - Bootstrapping : check_session au montage (restaure la session après refresh)
 * - Polling 60 s : warning / expired
 * - login / logout / refreshSession / hasPermission / hasRole
 */
export function AuthProvider({ children }) {
  const queryClient = useQueryClient();
  const [user, setUserState] = useState(null);
  const [bootstrapped, setBootstrapped] = useState(false);
  const [loading, setLoading] = useState(false);
  const [showWarning, setShowWarning] = useState(false);
  const [remaining, setRemaining] = useState(0);
  const [sessionExpired, setSessionExpired] = useState(false);

  // Restaure la session au chargement (cookie SSMUNIE / ssm_sid)
  useEffect(() => {
    let active = true;
    (async () => {
      try {
        const res = await api.get('/check_session.php');
        if (active) {
          setUserState(res.data.user ?? null);
          if (res.data.status === 'warning') {
            setShowWarning(true);
            setRemaining(res.data.remaining ?? 0);
          }
        }
      } catch {
        if (active) setUserState(null);
      } finally {
        if (active) setBootstrapped(true);
      }
    })();
    return () => {
      active = false;
    };
  }, []);

  // Polling de session
  useEffect(() => {
    if (!user) return undefined;
    let active = true;

    const poll = async () => {
      try {
        const res = await api.get('/check_session.php');
        if (!active) return;
        if (res.data.status === 'warning') {
          setShowWarning(true);
          setRemaining(res.data.remaining ?? 0);
        } else {
          setShowWarning(false);
        }
      } catch (e) {
        if (!active) return;
        const status = e?.response?.data?.status;
        if (status === 'expired' || status === 'unauthenticated') {
          setSessionExpired(true);
          setUserState(null);
          setShowWarning(false);
          window.location.href = '/login';
        }
      }
    };

    poll();
    const id = setInterval(poll, 60_000);
    return () => {
      active = false;
      clearInterval(id);
    };
  }, [user]);

  const login = useCallback(async (username, password) => {
    setLoading(true);
    try {
      const res = await api.post('/auth.php', { username, password });
      setUserState(res.data.user);
      setShowWarning(false);
      setSessionExpired(false);
      return res.data.user;
    } finally {
      setLoading(false);
    }
  }, []);

  const logout = useCallback(async () => {
    try {
      await api.post('/logout.php');
    } catch {
      /* ignore */
    }
    queryClient.clear();
    setUserState(null);
    setShowWarning(false);
    setSessionExpired(false);
    window.location.href = '/login';
  }, [queryClient]);

  const refreshSession = useCallback(async () => {
    await api.post('/refresh.php', { action: 'refresh' });
    setShowWarning(false);
    setRemaining(0);
  }, []);

  const setUser = useCallback((u) => {
    setUserState(u);
    if (u) {
      setShowWarning(false);
      setSessionExpired(false);
    }
  }, []);

  const hasPermission = useCallback(
    (code) => {
      const perms = user?.permissions ?? [];
      return perms.includes('*') || perms.includes(code);
    },
    [user]
  );

  const hasRole = useCallback((code) => (user?.roles ?? []).includes(code), [user]);

  const value = {
    user,
    bootstrapped,
    loading,
    showWarning,
    remaining,
    sessionExpired,
    login,
    logout,
    refreshSession,
    setUser,
    hasPermission,
    hasRole,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error('useAuth doit être utilisé dans <AuthProvider>');
  }
  return ctx;
}
