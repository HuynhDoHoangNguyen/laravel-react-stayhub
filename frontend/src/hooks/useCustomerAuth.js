import { useAuth } from './useAuth';

/**
 * Compatibility wrapper cho các component Customer đã sử dụng useCustomerAuth.
 * Chuyển tiếp 100% dữ liệu từ AuthContext dùng chung để tránh duplicate network calls.
 */
export function useCustomerAuth() {
    const { user, loading, error, refreshUser, logout } = useAuth();

    return {
        user,
        loading,
        error,
        refetch: refreshUser,
        logout,
    };
}

export default useCustomerAuth;
