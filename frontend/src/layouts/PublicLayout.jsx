import { useState, useEffect } from "react";
import { Link, NavLink, Outlet, useLocation } from "react-router-dom";
import { Building2, Menu, LogOut, Compass, Home } from "lucide-react";
import logoMark from "@/assets/logo-mark.png";
import { Toaster } from "@/components/ui/sonner";
import { useAuth } from "@/hooks/useAuth";
import AccountDropdown from "@/components/common/AccountDropdown";
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@/components/ui/sheet";

export default function PublicLayout() {
  const { user, loading, isAuthenticated, logout, isLoggingOut } = useAuth();
  const [mobileOpen, setMobileOpen] = useState(false);
  const location = useLocation();
  const isHomePage = location.pathname === "/";
  const [scrolled, setScrolled] = useState(false);

  useEffect(() => {
    if (!isHomePage) return;
    const onScroll = () => {
      setScrolled(window.scrollY > 20);
    };
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, [isHomePage]);

  const isOverlay = isHomePage && !scrolled;

  return (
    <div className="flex min-h-screen flex-col bg-background text-foreground">
      {/* Top Navigation */}
      <header
        className={
          isOverlay
            ? "absolute inset-x-0 top-0 z-30 border-b border-transparent bg-transparent transition-all duration-300"
            : isHomePage
            ? "fixed inset-x-0 top-0 z-30 border-b border-border/60 bg-background/90 backdrop-blur-md transition-all duration-300 shadow-xs"
            : "sticky top-0 z-30 border-b border-border/60 bg-background/90 backdrop-blur-md"
        }
      >
        {isOverlay && (
          <div
            aria-hidden
            className="pointer-events-none absolute inset-x-0 top-0 h-28 -z-10 transition-opacity duration-300"
            style={{
              background:
                "linear-gradient(180deg, rgba(253,250,246,0.85) 0%, rgba(253,250,246,0.5) 55%, rgba(253,250,246,0) 100%)",
            }}
          />
        )}
        <div className="mx-auto flex h-20 max-w-[1440px] items-center justify-between px-4 sm:px-6 md:px-10">
          {/* Brand */}
          <Link
            to="/"
            className="flex items-center gap-2.5 transition-opacity hover:opacity-85"
            aria-label="Trang chủ StayHub"
          >
            <img src={logoMark} alt="StayHub" className="h-9 w-9" />
            <span className="font-display text-2xl font-bold tracking-tight text-primary">
              StayHub
            </span>
          </Link>

          {/* Desktop Navigation links */}
          <nav aria-label="Chính" className="hidden items-center gap-8 md:flex">
            <NavLink
              to="/"
              className={({ isActive }) =>
                `text-sm font-medium transition-colors hover:text-primary ${
                  isActive ? "font-semibold text-primary" : "text-ink/80"
                }`
              }
            >
              Trang chủ
            </NavLink>
            <Link to="/properties" className={`text-sm font-medium transition-colors ${isOverlay ? 'text-white/90 hover:text-white' : 'text-ink hover:text-primary'}`}>Cơ sở lưu trú</Link>
            <NavLink
              to="/search"
              className={({ isActive }) =>
                `text-sm font-medium transition-colors hover:text-primary ${
                  isActive ? "font-semibold text-primary" : "text-ink/80"
                }`
              }
            >
              Khám phá chỗ nghỉ
            </NavLink>

            {/* Dynamic Role Navigation Link if Authenticated */}
            {isAuthenticated && user?.role === "CUSTOMER" && (
              <NavLink
                to="/customer"
                className={({ isActive }) =>
                  `text-sm font-medium transition-colors hover:text-primary ${
                    isActive ? "font-semibold text-primary" : "text-ink/80"
                  }`
                }
              >
                Khu vực Khách hàng
              </NavLink>
            )}

            {isAuthenticated && user?.role === "HOST" && (
              <NavLink
                to="/host"
                className={({ isActive }) =>
                  `text-sm font-medium transition-colors hover:text-primary ${
                    isActive ? "font-semibold text-primary" : "text-ink/80"
                  }`
                }
              >
                Kênh Đối tác (Host)
              </NavLink>
            )}

            {isAuthenticated && user?.role === "ADMIN" && (
              <NavLink
                to="/admin"
                className={({ isActive }) =>
                  `text-sm font-medium transition-colors hover:text-primary ${
                    isActive ? "font-semibold text-primary" : "text-ink/80"
                  }`
                }
              >
                Quản trị hệ thống
              </NavLink>
            )}
          </nav>

          {/* Actions: Guest 2 Buttons vs Authenticated Avatar Dropdown */}
          <div className="flex items-center gap-3">
            {/* Loading state */}
            {loading && (
              <div className="h-8 w-24 animate-pulse rounded-full bg-surface" />
            )}

            {/* Guest Actions (StayNest exact style: Đăng nhập + Đăng ký) */}
            {!loading && !isAuthenticated && (
              <div className="hidden items-center gap-4 sm:flex">
                <Link
                  to="/login"
                  className="text-sm font-medium text-ink/85 transition-colors hover:text-primary"
                >
                  Đăng nhập
                </Link>
                <Link
                  to="/login?mode=signup"
                  className="inline-flex items-center rounded-2xl bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground shadow-xs transition-colors duration-200 hover:bg-primary-mid"
                >
                  Đăng ký
                </Link>
              </div>
            )}

            {/* Authenticated Avatar Dropdown */}
            {!loading && isAuthenticated && (
              <AccountDropdown />
            )}

            {/* Mobile Hamburger Drawer */}
            <div className="md:hidden">
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

                  {/* Navigation Links */}
                  <div className="mt-6 flex-1 space-y-1.5 text-left">
                    <Link to="/properties" onClick={() => setMobileOpen(false)} className="flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium text-ink hover:bg-surface">
                      <Building2 className="h-4 w-4 text-primary" /><span>Cơ sở lưu trú</span>
                    </Link>
                    <Link
                      to="/"
                      onClick={() => setMobileOpen(false)}
                      className="flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium text-ink hover:bg-surface"
                    >
                      <Home className="h-4 w-4 text-primary" />
                      <span>Trang chủ</span>
                    </Link>
                    <Link
                      to="/search"
                      onClick={() => setMobileOpen(false)}
                      className="flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium text-ink hover:bg-surface"
                    >
                      <Compass className="h-4 w-4 text-primary" />
                      <span>Khám phá chỗ nghỉ</span>
                    </Link>

                    {isAuthenticated && user?.role === "CUSTOMER" && (
                      <Link
                        to="/customer"
                        onClick={() => setMobileOpen(false)}
                        className="flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium text-ink hover:bg-surface"
                      >
                        <Building2 className="h-4 w-4 text-primary" />
                        <span>Khu vực Khách hàng</span>
                      </Link>
                    )}

                    {isAuthenticated && user?.role === "HOST" && (
                      <Link
                        to="/host"
                        onClick={() => setMobileOpen(false)}
                        className="flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium text-ink hover:bg-surface"
                      >
                        <Building2 className="h-4 w-4 text-accent" />
                        <span>Kênh Đối tác (Host)</span>
                      </Link>
                    )}
                  </div>

                  {/* Mobile Actions */}
                  <div className="mt-auto space-y-2.5 border-t border-border/60 pt-4">
                    {!isAuthenticated ? (
                      <>
                        <Link
                          to="/login"
                          onClick={() => setMobileOpen(false)}
                          className="flex w-full items-center justify-center rounded-xl border border-border/80 bg-surface py-2.5 text-xs font-semibold text-ink hover:border-primary/40"
                        >
                          Đăng nhập
                        </Link>
                        <Link
                          to="/login?mode=signup"
                          onClick={() => setMobileOpen(false)}
                          className="flex w-full items-center justify-center rounded-xl bg-primary py-2.5 text-xs font-semibold text-primary-foreground shadow-xs hover:bg-primary-mid"
                        >
                          Đăng ký
                        </Link>
                      </>
                    ) : (
                      <button
                        type="button"
                        disabled={isLoggingOut}
                        onClick={() => {
                          setMobileOpen(false);
                          logout();
                        }}
                        className="flex w-full items-center justify-center gap-2 rounded-xl py-2.5 text-xs font-medium text-destructive hover:bg-destructive/10"
                      >
                        <LogOut className="h-3.5 w-3.5" />
                        <span>Đăng xuất</span>
                      </button>
                    )}
                  </div>
                </SheetContent>
              </Sheet>
            </div>
          </div>
        </div>
      </header>

      {/* Main Content */}
      <main className="flex-1">
        <Outlet />
      </main>

      {/* Footer */}
      <footer className="border-t border-border/60 bg-background">
        <div className="mx-auto max-w-[1440px] px-6 py-12 md:px-10 md:py-16 text-left">
          <div className="grid gap-10 md:grid-cols-3">
            <div>
              <div className="flex items-center gap-2.5">
                <img src={logoMark} alt="" className="h-8 w-8" />
                <span className="font-display text-2xl font-bold text-primary">StayHub</span>
              </div>
              <p className="mt-3.5 max-w-sm text-[14px] leading-relaxed text-muted-foreground">
                Hệ thống đặt phòng và quản lý lưu trú cao cấp, kết nối những không gian nghỉ dưỡng thanh lịch và trải nghiệm trọn vẹn.
              </p>
            </div>

            <div>
              <div className="text-[12px] font-semibold uppercase tracking-[0.14em] text-ink">
                Điều hướng
              </div>
              <ul className="mt-4 space-y-2 text-[14px] text-muted-foreground">
                <li>
                  <Link to="/" className="transition-colors hover:text-primary">Trang chủ</Link>
                </li>
                <li><Link to="/properties" className="transition-colors hover:text-primary">Cơ sở lưu trú</Link></li>
                <li>
                  <Link to="/search" className="transition-colors hover:text-primary">Tìm kiếm phòng</Link>
                </li>
                <li>
                  <Link to="/customer/invoices" className="transition-colors hover:text-primary">Hóa đơn của tôi</Link>
                </li>
                <li>
                  <Link to="/host/invoices" className="transition-colors hover:text-primary">Quản lý Hóa đơn Host</Link>
                </li>
              </ul>
            </div>

            <div>
              <div className="text-[12px] font-semibold uppercase tracking-[0.14em] text-ink">
                Hệ thống
              </div>
              <p className="mt-4 text-[13px] leading-relaxed text-muted-foreground">
                Được phát triển với React 19, Tailwind CSS v4, và Laravel Sanctum theo phong cách Quiet Luxury.
              </p>
            </div>
          </div>

          <div className="mt-12 border-t border-border/60 pt-6 text-center text-[13px] text-muted-foreground">
            © 2026 StayHub. Bản quyền được bảo lưu.
          </div>
        </div>
      </footer>

      {/* Global Toaster */}
      <Toaster position="top-center" />
    </div>
  );
}
