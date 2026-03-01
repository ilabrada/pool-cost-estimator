import { useState, useCallback } from 'react';
import { AuthContext } from './authContext';
import { login as apiLogin, logout as apiLogout } from '../utils/storage';

const TOKEN_KEY = 'pce_token';

export function AuthProvider({ children }) {
  const [authenticated, setAuthenticated] = useState(() => {
    return sessionStorage.getItem(TOKEN_KEY) !== null;
  });

  const login = useCallback(async (pin) => {
    const ok = await apiLogin(pin);
    if (ok) setAuthenticated(true);
    return ok;
  }, []);

  const logout = useCallback(() => {
    apiLogout();
    setAuthenticated(false);
  }, []);

  return (
    <AuthContext.Provider value={{ authenticated, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}
