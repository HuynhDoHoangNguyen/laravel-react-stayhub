import { useState } from 'react';
import { Link, NavLink, Outlet } from 'react-router-dom';
import {
    Compass,
    CalendarCheck,
    FileText,
    User,
    LogOut,
    Menu,
    LayoutDashboard,
} from 'lucide-react';
import logoMark from '@/assets/logo-mark.png';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Toaster } from '@/components/ui/sonner';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useAuth } from '@/hooks/useAuth';
import AccountDropdown from '@/components/common/AccountDropdown';

function initialsOf(name) {
    const parts = (name || '').trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) return 'KH';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return ((parts[0]?.[0] ?? '') + (parts[parts.length - 1]?.[0] ?? '')).toUpperCase();
}

const NAV_LINKS = [
    { to: '/customer', label: 'Tổng quan', end: true, icon: LayoutDashboard },
    { to: '/customer/bookings', label: 'Đơn đặt phòng', icon: CalendarCheck },
    { to: '/customer/invoices', label: 'Hóa đơn của tôi', icon: FileText },
    { to: '/customer/profile', label: 'Hồ sơ cá nhân', icon: User },
    { to: '/search', label: 'Khám phá chỗ nghỉ', icon: Compass },
];

export default function CustomerLayout() {
    const { user, loading, error, refreshUser, logout } = useAuth();
    const [mobileOpen, setMobileOpen] = useState(false);

    return (
        <div className="flex min-h-screen flex-col bg-background text-foreground">
            {/* Top Navigation Bar */}
            <header className="sticky top-0 z-40 border-b border-border/60 bg-background/95 backdrop-blur-md">
                <div className="mx-auto flex h-20 max-w-[1440px] items-center justify-between px-4 sm:px-6 md:px-10">
                    {/* Brand */}
                    <Link
                        to="/"
                        className="flex items-center gap-2.5 transition-opacity hover:opacity-85"
                        aria-label="Trang chủ StayHub"
                    >
                        <img src={logoMark} alt="StayHub" className="h-9 w-9" />
                        <div className="flex flex-col leading-tight">
                            <span className="font-display text-2xl font-bold tracking-tight text-primary">
                                StayHub
                            </span>
                            <span className="text-[10px] font-semibold uppercase tracking-[0.2em] text-accent">
                                ✦ Khách hàng
                            </span>
                        </div>
                    </Link>

                    {/* Desktop Navigation Links */}
                    <nav aria-label="Customer Navigation" className="hidden items-center gap-1.5 lg:flex">
                        {NAV_LINKS.map((link) => {
                            const Icon = link.icon;
                            return (
                                <NavLink
                                    key={link.to}
                                    to={link.to}
                                    end={link.end}
                                    className={({ isActive }) =>
                                        `inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-[13.5px] font-medium transition-all ${
                                            isActive
                                                ? 'bg-primary text-primary-foreground shadow-xs'
                                                : 'text-ink/80 hover:bg-surface hover:text-primary'
                                        }`
                                    }
                                >
                                    <Icon className="h-3.5 w-3.5 shrink-0 opacity-80" />
                                    <span>{link.label}</span>
                                </NavLink>
                            );
                        })}
                    </nav>

                    {/* Right User Identity & Dropdown */}
                    <div className="flex items-center gap-2.5 sm:gap-3">
                        {/* Loading State */}
                        {loading && (
                            <div className="flex h-9 w-24 animate-pulse items-center gap-2 rounded-full border border-border/50 bg-surface/70 px-2">
                                <div className="h-6 w-6 rounded-full bg-border/80" />
                                <div className="h-2.5 w-12 rounded bg-border/80" />
                            </div>
                        )}

                        {/* Unauthenticated State */}
                        {!loading && !user && (
                            <Link
                                to="/login?role=customer"
                                className="inline-flex items-center rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-primary-foreground shadow-xs hover:bg-primary-mid"
                            >
                                Đăng nhập
                            </Link>
                        )}

                        {/* Authenticated Account Dropdown */}
                        {!loading && user && (
                            <AccountDropdown />
                        )}

                        {/* Mobile Menu Trigger */}
                        <div className="lg:hidden">
                            <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
                                <SheetTrigger
                                    className="grid h-9 w-9 place-items-center rounded-xl border border-border/70 bg-surface text-ink transition-colors hover:text-primary"
                                    aria-label="Mở menu di động"
                                >
                                    <Menu className="h-4 w-4" />
                                </SheetTrigger>
                                <SheetContent side="right" className="flex flex-col p-6 text-left">
                                    <SheetHeader className="text-left">
                                        <SheetTitle className="flex items-center gap-2">
                                            <img src={logoMark} alt="StayHub" className="h-7 w-7" />
                                            <span className="font-display text-xl font-bold text-primary">StayHub</span>
                                        </SheetTitle>
                                    </SheetHeader>

                                    {/* Mobile User Identity */}
                                    <div className="mt-6 rounded-2xl border border-border/60 bg-surface/60 p-4">
                                        {user ? (
                                            <div className="flex items-center gap-3">
                                                <Avatar className="h-10 w-10 border border-primary/20">
                                                    <AvatarFallback className="bg-primary font-display font-semibold text-primary-foreground">
                                                        {initialsOf(user.name)}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div className="min-w-0 text-left">
                                                    <div className="truncate text-sm font-bold text-primary">
                                                        {user.name}
                                                    </div>
                                                    <div className="truncate text-xs text-muted-foreground">
                                                        {user.email}
                                                    </div>
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="text-center">
                                                <p className="text-xs text-muted-foreground">Chưa đăng nhập</p>
                                                <Link
                                                    to="/login?role=customer"
                                                    onClick={() => setMobileOpen(false)}
                                                    className="mt-2 inline-flex w-full items-center justify-center rounded-xl bg-primary px-3 py-2 text-xs font-semibold text-primary-foreground hover:bg-primary-mid"
                                                >
                                                    Đăng nhập tài khoản
                                                </Link>
                                            </div>
                                        )}
                                    </div>

                                    {/* Mobile Navigation List */}
                                    <div className="mt-6 flex-1 space-y-1.5 text-left">
                                        {NAV_LINKS.map((link) => {
                                            const Icon = link.icon;
                                            return (
                                                <NavLink
                                                    key={link.to}
                                                    to={link.to}
                                                    end={link.end}
                                                    onClick={() => setMobileOpen(false)}
                                                    className={({ isActive }) =>
                                                        `flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium transition-colors ${
                                                            isActive
                                                                ? 'bg-primary text-primary-foreground font-semibold'
                                                                : 'text-ink hover:bg-surface'
                                                        }`
                                                    }
                                                >
                                                    <Icon className="h-4 w-4" />
                                                    <span>{link.label}</span>
                                                </NavLink>
                                            );
                                        })}
                                    </div>

                                    {/* Mobile Logout Action */}
                                    {user && (
                                        <div className="mt-auto border-t border-border/60 pt-4">
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setMobileOpen(false);
                                                    logout();
                                                }}
                                                className="flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl py-2.5 text-xs font-medium text-destructive hover:bg-destructive/10"
                                            >
                                                <LogOut className="h-3.5 w-3.5" />
                                                <span>Đăng xuất</span>
                                            </button>
                                        </div>
                                    )}
                                </SheetContent>
                            </Sheet>
                        </div>
                    </div>
                </div>
            </header>

            {/* Main Content Area */}
            <main className="mx-auto w-full max-w-[1440px] flex-1 px-4 py-8 sm:px-6 lg:px-10">
                <Outlet context={{ user, loading, error, refetch: refreshUser, logout }} />
            </main>

            {/* Footer */}
            <footer className="border-t border-border/60 bg-background py-8 text-xs text-muted-foreground">
                <div className="mx-auto flex max-w-[1440px] flex-wrap items-center justify-between gap-4 px-4 sm:px-6 md:px-10 text-left">
                    <div className="flex items-center gap-2">
                        <span className="font-display text-base font-bold text-primary">StayHub</span>
                        <span>— Trải nghiệm kỳ nghỉ thanh lịch</span>
                    </div>
                    <div>Hệ thống quản lý đặt phòng & dịch vụ lưu trú Quiet Luxury</div>
                </div>
            </footer>

            {/* Global Toaster */}
            <Toaster position="top-center" />
        </div>
    );
}