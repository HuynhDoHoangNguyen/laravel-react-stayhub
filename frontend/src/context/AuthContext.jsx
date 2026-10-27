import { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import { authService } from '../services/authService';
import { AuthContext } from './auth-context-def';

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);
    const [authError, setAuthError] = useState(null);
    const [notice, setNotice] = useState(null);
    const [isLoggingOut, setIsLoggingOut] = useState(false);
    const versionRef = useRef(0);
    const logoutPromiseRef = useRef(null);
    const loginPendingRef = useRef(false);
    const invalidatePendingRequests = useCallback(() => { ++versionRef.current; }, []);

    const restoreSession = useCallback(async () => {
        const version = ++versionRef.current;
        setLoading(true);
        setAuthError(null);
        try {
            const restoredUser = await authService.restoreSession();
            if (version === versionRef.current) setUser(restoredUser);
        } catch (error) {
            if (version === versionRef.current) {
                if (error.status === 401 || error.code) {
                    setUser(null);
                    if (error.code) setNotice({ type: 'error', message: error.message });
                } else {
                    // Retain our knowledge of the user; the route guard hides protected data.
                    setAuthError({ message: error.message, operation: 'restore' });
                }
            }
        } finally {
            if (version === versionRef.current) setLoading(false);
        }
    }, []);

    useEffect(() => {
        let active = true;
        const unsubscribe = authService.subscribeToAuthFailures((error, version) => {
            if (version !== versionRef.current) return;
            invalidatePendingRequests();
            setUser(null);
            setAuthError(null);
            setLoading(false);
            if (error.code) setNotice({ type: 'error', message: error.message });
        }, () => versionRef.current);

        // Cancel StrictMode's first mount before issuing a restore.
        Promise.resolve().then(() => { if (active) void restoreSession(); });
        return () => {
            active = false;
            unsubscribe();
            invalidatePendingRequests();
        };
    }, [restoreSession, invalidatePendingRequests]);

    const login = useCallback(async (credentials) => {
        if (loginPendingRef.current) return null;
        loginPendingRef.current = true;
        const version = ++versionRef.current;
        setLoading(true);
        setUser(null);
        setAuthError(null);
        setNotice(null);
        try {
            const authenticatedUser = await authService.login(credentials);
            if (version !== versionRef.current) return null;
            setUser(authenticatedUser);
            setLoading(false);
            return authenticatedUser;
        } finally {
            loginPendingRef.current = false;
            if (version === versionRef.current) setLoading(false);
        }
    }, []);

    const logout = useCallback(() => {
        if (logoutPromiseRef.current) return logoutPromiseRef.current;
        const version = ++versionRef.current;
        setIsLoggingOut(true);
        setAuthError(null);
        const promise = (async () => {
            try {
                const response = await authService.logout();
                if (version === versionRef.current) {
                    setUser(null);
                    setLoading(false);
                    setNotice({ type: 'success', message: response.message });
                }
                return true;
            } catch (error) {
                if (error.status === 401 || error.code) {
                    if (version === versionRef.current) setUser(null);
                    return true;
                }
                if (version === versionRef.current) {
                    setAuthError({ message: 'Chưa thể xác nhận đăng xuất. ' + error.message, operation: 'logout' });
                }
                return false;
            } finally {
                logoutPromiseRef.current = null;
                setIsLoggingOut(false);
            }
        })();
        logoutPromiseRef.current = promise;
        return promise;
    }, []);

    const dismissNotice = useCallback(() => setNotice(null), []);
    const value = useMemo(() => ({
        user, loading, authError, error: authError?.message || null, notice, isLoggingOut,
        isAuthenticated: !!user, role: user?.role || null,
        login, logout, retry: restoreSession, refreshUser: restoreSession, dismissNotice,
    }), [user, loading, authError, notice, isLoggingOut, login, logout, restoreSession, dismissNotice]);

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}
