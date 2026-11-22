import { Link, Navigate, useLocation, Outlet } from "react-router-dom";
import { Sparkles, Star, CreditCard, Lock, User } from "lucide-react";
import logoMark from "@/assets/logo-mark.png";
import { Toaster } from "@/components/ui/sonner";
import { useAuth } from "@/hooks/useAuth";
import { useHostApproval } from "@/hooks/useHostApproval";
import AccountDropdown from "@/components/common/AccountDropdown";
import {
  BackIcon,
  BellIcon,
  BookingsIcon,
  ConsoleIcon,
  HotelIcon,
  InvoiceIcon,
  SearchIcon,
  SignOutIcon,
} from "@/components/manager/icons";
import {
  Sidebar,
  SidebarContent,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarProvider,
  SidebarTrigger,
  useSidebar,
} from "@/components/ui/sidebar";

const NAV = [
  { to: "/host", label: "Tổng quan", icon: ConsoleIcon, exact: true },
  { to: "/host/invoices", label: "Quản lý Hóa đơn", icon: InvoiceIcon },
  { to: "/host/commissions", label: "Hoa hồng & Đối soát", icon: CreditCard },
  { to: "/host/reviews", label: "Đánh giá của khách", icon: Star },
  { to: "/host/bookings", label: "Đơn đặt phòng", icon: BookingsIcon },
  { to: "/host/services", label: "Dịch vụ", icon: Sparkles },
  { to: "/host/properties", label: "Cơ sở lưu trú", icon: HotelIcon },
];

export default function HostLayout() {
  const { pathname } = useLocation();
  const { logout, isLoggingOut } = useAuth();
  const { isPendingHost } = useHostApproval();

  if (isPendingHost && pathname !== "/host/profile") {
    return <Navigate to="/host/profile" replace />;
  }

  return (
    <SidebarProvider>
      <div className="flex min-h-screen w-full bg-background text-foreground">
        {/* Sidebar */}
        <Sidebar collapsible="icon" className="border-r border-border/60">
          <SidebarHeader className="border-b border-border/60 px-3 py-3 group-data-[collapsible=icon]:px-0">
            <BrandLink isPendingHost={isPendingHost} />
          </SidebarHeader>

          <SidebarContent>
            <SidebarGroup>
              <SidebarGroupLabel>Menu Quản lý</SidebarGroupLabel>
              <SidebarGroupContent>
                <SidebarMenu>
                  {isPendingHost && (
                    <SidebarMenuItem>
                      <SidebarMenuButton asChild isActive={pathname === "/host/profile"} tooltip="Hồ sơ cá nhân">
                        <Link to="/host/profile" className="flex items-center gap-2.5">
                          <User className="h-4 w-4" />
                          <span>Hồ sơ cá nhân</span>
                        </Link>
                      </SidebarMenuButton>
                    </SidebarMenuItem>
                  )}
                  {NAV.map((item) => {
                    if (isPendingHost) {
                      return (
                        <SidebarMenuItem key={item.to}>
                          <SidebarMenuButton
                            disabled
                            tooltip={`${item.label} (Chờ Quản trị viên phê duyệt mở khóa)`}
                            className="cursor-not-allowed opacity-45"
                          >
                            <item.icon className="h-4 w-4" />
                            <span className="flex-1 truncate">{item.label}</span>
                            <Lock className="h-3.5 w-3.5 text-amber-700" />
                          </SidebarMenuButton>
                        </SidebarMenuItem>
                      );
                    }
                    const active = item.to === "/host/services"
                      ? pathname === item.to || /^\/host\/properties\/\d+\/services(?:\/|$)/.test(pathname)
                      : item.exact
                      ? pathname === item.to
                      : pathname === item.to || pathname.startsWith(item.to + "/");
                    return (
                      <SidebarMenuItem key={item.to}>
                        <SidebarMenuButton asChild isActive={active} tooltip={item.label}>
                          <Link to={item.to} className="flex items-center gap-2.5">
                            <item.icon className="h-4 w-4" />
                            <span>{item.label}</span>
                          </Link>
                        </SidebarMenuButton>
                      </SidebarMenuItem>
                    );
                  })}
                </SidebarMenu>
              </SidebarGroupContent>
            </SidebarGroup>

            <SidebarGroup>
              <SidebarGroupLabel>Tài khoản</SidebarGroupLabel>
              <SidebarGroupContent>
                <SidebarMenu>
                  <SidebarMenuItem>
                    <SidebarMenuButton asChild tooltip="Về trang chủ">
                      <Link to="/" className="flex items-center gap-2.5">
                        <BackIcon className="h-4 w-4" />
                        <span>Về trang chủ</span>
                      </Link>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                  <SidebarMenuItem>
                    <SidebarMenuButton
                      tooltip="Đăng xuất"
                      onClick={logout}
                      disabled={isLoggingOut}
                      className="cursor-pointer text-destructive hover:bg-destructive/10 hover:text-destructive disabled:opacity-50"
                    >
                      <SignOutIcon className="h-4 w-4" />
                      <span>{isLoggingOut ? "Đang đăng xuất..." : "Đăng xuất"}</span>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                </SidebarMenu>
              </SidebarGroupContent>
            </SidebarGroup>
          </SidebarContent>
        </Sidebar>

        {/* Main Content Area */}
        <div className="flex min-w-0 flex-1 flex-col">
          {/* Topbar */}
          <header className="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-border/60 bg-gradient-to-b from-background/95 to-background/80 px-4 backdrop-blur-xl sm:px-6">
            <SidebarTrigger className="text-ink" />

            <div className="hidden items-center gap-2 rounded-full border border-border/60 bg-background/70 px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-[0.2em] text-muted-foreground shadow-[0_1px_0_rgba(255,255,255,0.6)_inset] md:inline-flex">
              <ConsoleIcon className="h-3.5 w-3.5 text-primary" />
              Host Console
            </div>

            <div className="ml-auto flex items-center gap-3">
              <button
                type="button"
                aria-label="Tìm kiếm"
                className="hidden h-9 w-9 items-center justify-center rounded-full border border-border/60 bg-background/70 text-muted-foreground transition-all hover:border-primary/40 hover:text-primary sm:inline-flex"
              >
                <SearchIcon className="h-4 w-4" />
              </button>
              <button
                type="button"
                aria-label="Thông báo"
                className="relative inline-flex h-9 w-9 items-center justify-center rounded-full border border-border/60 bg-background/70 text-muted-foreground transition-all hover:border-primary/40 hover:text-primary"
              >
                <BellIcon className="h-4 w-4" />
                <span className="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-gradient-to-br from-amber-400 to-rose-500 ring-2 ring-background" />
              </button>

              {/* Authenticated Host Account Dropdown */}
              <AccountDropdown />
            </div>
          </header>

          {/* Page Content */}
          <main className="flex-1 p-4 sm:p-6 lg:p-8">
            <Outlet />
          </main>
        </div>
      </div>
      <Toaster position="top-center" />
    </SidebarProvider>
  );
}

function BrandLink({ isPendingHost = false }) {
  const { state } = useSidebar();
  const collapsed = state === "collapsed";
  return (
    <Link
      to={isPendingHost ? "/host/profile" : "/host"}
      className={
        collapsed
          ? "mx-auto flex h-9 w-9 items-center justify-center text-primary transition-opacity hover:opacity-80"
          : "flex items-center gap-2.5 px-1 text-primary transition-opacity hover:opacity-80"
      }
      aria-label="StayHub Host Console"
    >
      <img src={logoMark} alt="" className="h-9 w-9 shrink-0" />
      {!collapsed && (
        <span className="flex min-w-0 flex-col leading-tight">
          <span className="truncate font-display text-[19px] font-bold">StayHub</span>
          <span className="text-[10px] font-semibold uppercase tracking-[0.24em] text-accent">
            Host Console
          </span>
        </span>
      )}
    </Link>
  );
}
