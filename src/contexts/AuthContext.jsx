import { useState, useCallback } from 'react';
import { AuthContext } from './authContext';
import { checkPin } from '../utils/storage';

const SESSION_KEY = 'pce_session';

export function AuthProvider({ children }) {
  const [authenticated, setAuthenticated] = useState(() => {
    return sessionStorage.getItem(SESSION_KEY) === 'true';
  });

  const login = useCallback((pin) => {
    if (checkPin(pin)) {
      sessionStorage.setItem(SESSION_KEY, 'true');
      setAuthenticated(true);
      return true;
    }
    return false;
  }, []);

  const logout = useCallback(() => {
    sessionStorage.removeItem(SESSION_KEY);
    setAuthenticated(false);
  }, []);

  return (
    <AuthContext.Provider value={{ authenticated, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}
