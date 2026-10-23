import { Navigate, Outlet, useLocation, Link } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import { ShieldAlert, ArrowRight, LogOut, Loader2 } from 'lucide-react';
import logoMark from '@/assets/logo-mark.png';

/**
 * Route Guard bảo vệ các khu vực yêu cầu đăng nhập và phân quyền theo Role.
 *
 * @param {Object} props
 * @param {string[]} [props.allowedRoles] Danh sách role được phép truy cập (ví dụ: ['CUSTOMER'], ['HOST'], ['ADMIN'])
 */
export default function ProtectedRoute({ allowedRoles }) {
    const { user, loading, isAuthenticated, logout, isLoggingOut } = useAuth();
    const location = useLocation();

    // 1. Khi đang xác minh session Sanctum: hiển thị màn hình chờ Quiet Luxury, không vội redirect
    if (loading) {
        return (
            <div className="flex min-h-[60vh] flex-col items-center justify-center bg-background px-4 text-center">
                <div className="relative mb-4">
                    <img src={logoMark} alt="StayHub" className="h-12 w-12 animate-pulse opacity-90" />
                    <Loader2 className="absolute -bottom-1 -right-1 h-5 w-5 animate-spin text-primary" />
                </div>
                <p className="font-display text-lg font-semibold text-primary">StayHub</p>
                <p className="mt-1 text-xs text-muted-foreground">Đang xác thực phiên làm việc an toàn...</p>
            </div>
        );
    }

    // 2. Khi chưa đăng nhập (Guest): Chuyển hướng an toàn tới trang /login kèm returnTo
    if (!isAuthenticated) {
        const returnTo = location.pathname + location.search;
        const targetRole = allowedRoles?.[0]?.toLowerCase() || 'customer';
        return (
            <Navigate
                to={`/login?role=${targetRole}&returnTo=${encodeURIComponent(returnTo)}`}
                replace
                state={{ from: location }}
            />
        );
    }

    // 3. Khi đã đăng nhập nhưng vai trò không khớp (Role Mismatch / Access Denied)
    if (allowedRoles && allowedRoles.length > 0 && !allowedRoles.includes(user.role)) {
        const roleLabelMap = {
            CUSTOMER: 'Khách hàng hội viên',
            HOST: 'Đối tác lưu trú (Host)',
            ADMIN: 'Quản trị viên (Admin)',
        };

        const homeForRole = {
            CUSTOMER: '/customer',
            HOST: '/host',
            ADMIN: '/admin',
        }[user.role] || '/';

        return (
            <div className="flex min-h-[70vh] items-center justify-center bg-background p-4 sm:p-6">
                <div className="w-full max-w-lg rounded-3xl border border-border/70 bg-white p-8 text-center shadow-xs sm:p-10">
                    <div className="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-amber-50 text-amber-700">
                        <ShieldAlert className="h-8 w-8" />
                    </div>

                    <p className="mt-6 text-[11px] font-semibold uppercase tracking-[0.24em] text-accent">
                        ✦ Quyền truy cập
                    </p>
                    <h1 className="mt-1 font-display text-2xl font-bold text-primary sm:text-3xl">
                        Khu vực giới hạn
                    </h1>
                    <p className="mx-auto mt-3 max-w-md text-xs leading-relaxed text-muted-foreground">
                        Bạn đang đăng nhập với vai trò <strong className="text-ink">{roleLabelMap[user.role] || user.role}</strong>.
                        Trang này chỉ dành riêng cho tài khoản có vai trò <strong className="text-ink">{allowedRoles.map(r => roleLabelMap[r] || r).join(', ')}</strong>.
                    </p>

                    <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        <Link
                            to={homeForRole}
                            className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-xs font-semibold text-primary-foreground shadow-xs hover:bg-primary-mid sm:w-auto"
                        >
                            <span>Về khu vực của bạn</span>
                            <ArrowRight className="h-3.5 w-3.5" />
                        </Link>
                        <button
                            type="button"
                            onClick={logout}
                            disabled={isLoggingOut}
                            className="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-border/80 bg-surface px-5 py-2.5 text-xs font-medium text-destructive hover:bg-destructive/10 sm:w-auto"
                        >
                            <LogOut className="h-3.5 w-3.5" />
                            <span>Đăng xuất tài khoản</span>
                        </button>
                    </div>
                </div>
            </div>
        );
    }

    // 4. Xác thực và vai trò hoàn toàn hợp lệ
    return <Outlet />;
}
