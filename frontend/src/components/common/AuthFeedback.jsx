import { useAuth } from '../../hooks/useAuth';

export default function AuthFeedback() {
    const { notice, authError, logout, retry, dismissNotice, loading, isLoggingOut } = useAuth();
    const message = authError?.message || notice?.message;
    if (!message) return null;

    return (
        <div role={authError || notice?.type === 'error' ? 'alert' : 'status'}
            className="relative z-50 flex flex-wrap items-center justify-center gap-3 border-b border-border bg-surface px-4 py-3 text-sm text-ink">
            <span>{message}</span>
            {authError ? (
                <button type="button" disabled={loading || isLoggingOut}
                    onClick={authError.operation === 'logout' ? logout : retry}
                    className="rounded-lg border border-border px-3 py-1 font-semibold text-primary disabled:opacity-50">
                    {authError.operation === 'logout' ? 'Thử đăng xuất lại' : 'Thử kết nối lại'}
                </button>
            ) : (
                <button type="button" onClick={dismissNotice} className="rounded-lg border border-border px-3 py-1">Đóng</button>
            )}
        </div>
    );
}
