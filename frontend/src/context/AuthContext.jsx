import { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import api from '../api/axios';
import { AuthContext } from './auth-context-def';

/**
 * AuthProvider quản lý phiên xác thực tập trung cho toàn ứng dụng StayHub qua Laravel Sanctum.
 * Kiểm soát race conditions, ngăn chặn gọi lặp /api/me và đồng bộ trạng thái giữa tất cả layouts.
 */
export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [isLoggingOut, setIsLoggingOut] = useState(false);

    // Dùng request counter để tránh response cũ ghi đè state mới khi có race condition
    const requestIdRef = useRef(0);

    const restoreSession = useCallback(async () => {
        const currentReqId = ++requestIdRef.current;

        try {
            const response = await api.get('/api/me');
            if (currentReqId === requestIdRef.current) {
                setUser(response.data?.user || null);
                setError(null);
            }
        } catch (err) {
            if (currentReqId === requestIdRef.current) {
                const status = err.response?.status;
                if (status === 401) {
                    // Chưa đăng nhập -> Trạng thái Guest bình thường, không coi là lỗi hệ thống
                    setUser(null);
                    setError(null);
                } else {
                    // Lỗi mạng hoặc 5xx -> không vội xóa session nếu là chập chờn mạng
                    setUser(null);
                    setError(err.response?.data?.message || 'Không thể kết nối đến máy chủ xác thực.');
                }
            }
        } finally {
            if (currentReqId === requestIdRef.current) {
                setLoading(false);
            }
        }
    }, []);

    // Khởi tạo khôi phục session đúng 1 lần khi ứng dụng mount
    useEffect(() => {
        let isMounted = true;
        const currentReqId = ++requestIdRef.current;

        const checkAuth = async () => {
            try {
                const response = await api.get('/api/me');
                if (isMounted && currentReqId === requestIdRef.current) {
                    setUser(response.data?.user || null);
                    setError(null);
                }
            } catch (err) {
                if (isMounted && currentReqId === requestIdRef.current) {
                    const status = err.response?.status;
                    if (status === 401) {
                        setUser(null);
                        setError(null);
                    } else {
                        setUser(null);
                        setError(err.response?.data?.message || 'Không thể kết nối đến máy chủ xác thực.');
                    }
                }
            } finally {
                if (isMounted && currentReqId === requestIdRef.current) {
                    setLoading(false);
                }
            }
        };

        checkAuth();

        return () => {
            isMounted = false;
        };
    }, []);

    // Hàm đăng nhập tập trung
    const login = useCallback(async ({ email, password }) => {
        const currentReqId = ++requestIdRef.current;
        setError(null);

        // 1. Khởi tạo CSRF cookie
        await api.get('/sanctum/csrf-cookie');

        // 2. Gửi request đăng nhập
        const response = await api.post('/login', { email, password });
        const authenticatedUser = response.data?.user;

        if (currentReqId === requestIdRef.current) {
            setUser(authenticatedUser || null);
            setError(null);
        }

        return authenticatedUser;
    }, []);

    // Hàm đăng xuất tập trung
    const logout = useCallback(async () => {
        setIsLoggingOut(true);
        try {
            await api.post('/logout');
        } catch {
            // Vẫn xóa state client nếu server gặp lỗi mạng
        } finally {
            ++requestIdRef.current;
            setUser(null);
            setError(null);
            setIsLoggingOut(false);
        }
    }, []);

    const value = useMemo(() => ({
        user,
        loading,
        error,
        isLoggingOut,
        isAuthenticated: !!user,
        role: user?.role || null,
        login,
        logout,
        retry: restoreSession,
        refreshUser: restoreSession,
    }), [user, loading, error, isLoggingOut, login, logout, restoreSession]);

    return (
        <AuthContext.Provider value={value}>
            {children}
        </AuthContext.Provider>
    );
}
