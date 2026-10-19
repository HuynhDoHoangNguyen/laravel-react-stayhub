import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
    LayoutDashboard,
    CalendarCheck,
    FileText,
    User,
    Building2,
    ShieldCheck,
    LogOut,
    ChevronDown,
    Loader2,
} from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useAuth } from '@/hooks/useAuth';

function initialsOf(name) {
    const parts = (name || '').trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) return 'SH';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return ((parts[0]?.[0] ?? '') + (parts[parts.length - 1]?.[0] ?? '')).toUpperCase();
}

/**
 * Account Dropdown menu hiển thị danh tính và các lối tắt tương ứng theo vai trò thật (StayNest UserMenu pattern).
 */
export default function AccountDropdown({ triggerClassName, align = 'end' }) {
    const { user, logout, isLoggingOut } = useAuth();
    const [open, setOpen] = useState(false);
    const navigate = useNavigate();

    if (!user) return null;

    const roleBadges = {
        CUSTOMER: { label: 'Khách hàng hội viên', icon: User, tint: 'bg-primary/10 text-primary' },
        HOST: { label: 'Đối tác lưu trú (Host)', icon: Building2, tint: 'bg-accent/15 text-accent' },
        ADMIN: { label: 'Quản trị viên (Admin)', icon: ShieldCheck, tint: 'bg-rose-50 text-rose-700' },
    };

    const currentBadge = roleBadges[user.role] || {
        label: user.role,
        icon: User,
        tint: 'bg-surface text-ink',
    };
    const BadgeIcon = currentBadge.icon;

    const handleLogout = async () => {
        setOpen(false);
        await logout();
        navigate('/');
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    aria-label="Mở menu tài khoản"
                    className={
                        triggerClassName ||
                        'group inline-flex cursor-pointer items-center gap-2 rounded-full border border-border/70 bg-surface/70 py-1 pl-1 pr-3 text-xs font-medium text-ink shadow-xs outline-none transition-all hover:border-primary/40 hover:bg-surface focus-visible:ring-2 focus-visible:ring-primary/40'
                    }
                >
                    <Avatar className="h-7 w-7 border border-primary/20">
                        <AvatarFallback className="bg-gradient-to-br from-primary to-primary-mid font-display text-[11px] font-semibold text-primary-foreground">
                            {initialsOf(user.name)}
                        </AvatarFallback>
                    </Avatar>
                    <span className="hidden max-w-[120px] truncate text-xs font-semibold text-ink sm:inline">
                        {user.name}
                    </span>
                    <ChevronDown className="h-3.5 w-3.5 text-muted-foreground transition-transform group-data-[state=open]:rotate-180" />
                </button>
            </PopoverTrigger>

            <PopoverContent
                align={align}
                sideOffset={8}
                className="w-64 rounded-2xl border-border/70 bg-background/95 p-2 shadow-[0_20px_50px_-20px_rgba(26,46,32,0.35)] backdrop-blur text-left z-50"
            >
                {/* User Header */}
                <div className="px-3 py-2.5">
                    <div className="font-display text-[15px] font-bold leading-tight text-primary">
                        {user.name}
                    </div>
                    <div className="mt-0.5 truncate text-[11.5px] font-normal text-muted-foreground">
                        {user.email}
                    </div>
                    <div className={`mt-2 inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[10.5px] font-medium ${currentBadge.tint}`}>
                        <BadgeIcon className="h-3 w-3" />
                        <span>{currentBadge.label}</span>
                    </div>
                </div>

                <div className="my-1 h-px bg-border/60" />

                {/* Role-Specific Navigation Menu */}
                <div className="space-y-0.5">
                    {/* CUSTOMER Menu */}
                    {user.role === 'CUSTOMER' && (
                        <>
                            <Link
                                to="/customer"
                                onClick={() => setOpen(false)}
                                className="flex items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] font-medium text-ink transition-colors hover:bg-primary/[0.06] hover:text-primary"
                            >
                                <LayoutDashboard className="h-4 w-4" />
                                <span>Tổng quan tài khoản</span>
                            </Link>

                            <Link
                                to="/customer/bookings"
                                onClick={() => setOpen(false)}
                                className="flex items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] font-medium text-ink transition-colors hover:bg-primary/[0.06] hover:text-primary"
                            >
                                <CalendarCheck className="h-4 w-4" />
                                <span>Đơn đặt phòng của tôi</span>
                            </Link>

                            <Link
                                to="/customer/invoices"
                                onClick={() => setOpen(false)}
                                className="flex items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] font-medium text-ink transition-colors hover:bg-primary/[0.06] hover:text-primary"
                            >
                                <FileText className="h-4 w-4" />
                                <span>Hóa đơn & Thanh toán</span>
                            </Link>

                            <Link
                                to="/customer/profile"
                                onClick={() => setOpen(false)}
                                className="flex items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] font-medium text-ink transition-colors hover:bg-primary/[0.06] hover:text-primary"
                            >
                                <User className="h-4 w-4" />
                                <span>Hồ sơ cá nhân</span>
                            </Link>
                        </>
                    )}

                    {/* HOST Menu */}
                    {user.role === 'HOST' && (
                        <>
                            <Link
                                to="/host"
                                onClick={() => setOpen(false)}
                                className="flex items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] font-medium text-ink transition-colors hover:bg-primary/[0.06] hover:text-primary"
                            >
                                <LayoutDashboard className="h-4 w-4" />
                                <span>Kênh quản lý Host</span>
                            </Link>

                            <Link
                                to="/host/invoices"
                                onClick={() => setOpen(false)}
                                className="flex items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] font-medium text-ink transition-colors hover:bg-primary/[0.06] hover:text-primary"
                            >
                                <FileText className="h-4 w-4" />
                                <span>Quản lý Hóa đơn</span>
                            </Link>

                            <Link
                                to="/host/bookings"
                                onClick={() => setOpen(false)}
                                className="flex items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] font-medium text-ink transition-colors hover:bg-primary/[0.06] hover:text-primary"
                            >
                                <CalendarCheck className="h-4 w-4" />
                                <span>Đơn đặt phòng</span>
                            </Link>

                            <Link
                                to="/host/properties"
                                onClick={() => setOpen(false)}
                                className="flex items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] font-medium text-ink transition-colors hover:bg-primary/[0.06] hover:text-primary"
                            >
                                <Building2 className="h-4 w-4" />
                                <span>Cơ sở lưu trú</span>
                            </Link>
                        </>
                    )}

                    {/* ADMIN Menu */}
                    {user.role === 'ADMIN' && (
                        <>
                            <Link
                                to="/admin"
                                onClick={() => setOpen(false)}
                                className="flex items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] font-medium text-ink transition-colors hover:bg-primary/[0.06] hover:text-primary"
                            >
                                <ShieldCheck className="h-4 w-4" />
                                <span>Bảng điều khiển Admin</span>
                            </Link>
                        </>
                    )}

                    <div className="my-1 h-px bg-border/60" />

                    {/* Logout Button */}
                    <button
                        type="button"
                        disabled={isLoggingOut}
                        onClick={handleLogout}
                        className="flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] font-medium text-destructive transition-colors hover:bg-destructive/10 disabled:opacity-50"
                    >
                        {isLoggingOut ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                            <LogOut className="h-4 w-4" />
                        )}
                        <span>{isLoggingOut ? 'Đang đăng xuất...' : 'Đăng xuất'}</span>
                    </button>
                </div>
            </PopoverContent>
        </Popover>
    );
}
