import { useRef } from "react";
import { Link, useLocation, Outlet } from "react-router-dom";
import { ShieldCheck } from "lucide-react";
import logoMark from "@/assets/logo-mark.png";
import { Toaster } from "@/components/ui/sonner";
import { useAuth } from "@/hooks/useAuth";
import AccountDropdown from "@/components/common/AccountDropdown";
import {
  BackIcon,
  BookingsIcon,
  ConsoleIcon,
  HotelIcon,
  InvoiceIcon,
  SignOutIcon,
  UsersIcon,
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

const ADMIN_NAV = [
  { to: "/admin", label: "Tổng quan", icon: ConsoleIcon, exact: true },
  { to: "/admin/statistics", label: "Thống kê hệ thống", icon: ConsoleIcon },
  { to: "/admin/user-management", label: "Tài khoản", icon: UsersIcon },
  { to: "/admin/properties", label: "Cơ sở lưu trú", icon: HotelIcon },
  { to: "/admin/bookings", label: "Đơn đặt phòng", icon: BookingsIcon },
  { to: "/admin/invoices", label: "Hóa đơn hệ thống", icon: InvoiceIcon },
  { to: "/admin/commissions", label: "Đối soát hoa hồng", icon: ShieldCheck },
];

export default function AdminLayout() {
  const { pathname } = useLocation();
  const { logout, isLoggingOut } = useAuth();
  const navigationRef = useRef(null);

  return (
    <SidebarProvider>
      <div className="flex min-h-screen w-full bg-background text-foreground">
        {/* Sidebar */}
        <Sidebar collapsible="icon" className="border-r border-border/60" mobileInitialFocusRef={navigationRef}>
          <SidebarHeader className="border-b border-border/60 px-3 py-3 group-data-[collapsible=icon]:px-0">
            <BrandLink />
          </SidebarHeader>

          <SidebarContent>
            <SidebarGroup ref={navigationRef} role="navigation" aria-label="Điều hướng Admin" tabIndex={-1}
              className="focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary">
              <SidebarGroupLabel>Quản trị hệ thống</SidebarGroupLabel>
              <SidebarGroupContent>
                <SidebarMenu>
                  {ADMIN_NAV.map((item) => {
                    const active = item.exact
                      ? pathname === item.to
                      : pathname === item.to || pathname.startsWith(item.to + "/");
                    return (
                      <SidebarMenuItem key={item.to}>
                        <SidebarMenuButton asChild isActive={active} tooltip={item.label}>
                          <AdminSidebarLink to={item.to} aria-current={active ? "page" : undefined} className="flex items-center gap-2.5">
                            <item.icon className="h-4 w-4" />
                            <span>{item.label}</span>
                          </AdminSidebarLink>
                        </SidebarMenuButton>
                      </SidebarMenuItem>
                    );
                  })}
                </SidebarMenu>
              </SidebarGroupContent>
            </SidebarGroup>

            <SidebarGroup>
              <SidebarGroupLabel>Phiên đăng nhập</SidebarGroupLabel>
              <SidebarGroupContent>
                <SidebarMenu>
                  <SidebarMenuItem>
                    <SidebarMenuButton asChild tooltip="Về trang chủ">
                      <AdminSidebarLink to="/" className="flex items-center gap-2.5">
                        <BackIcon className="h-4 w-4" />
                        <span>Về trang chủ</span>
                      </AdminSidebarLink>
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
              <ShieldCheck className="h-3.5 w-3.5 text-primary" />
              Quản trị StayHub
            </div>

            <div className="ml-auto">
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

function AdminSidebarLink({ onClick, ...props }) {
  const { isMobile, setOpenMobile } = useSidebar();

  return <Link {...props} onClick={(event) => {
    onClick?.(event);
    if (isMobile && !event.defaultPrevented && event.button === 0
      && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey) {
      setOpenMobile(false);
    }
  }} />;
}

function BrandLink() {
  const { state } = useSidebar();
  const collapsed = state === "collapsed";
  return (
    <AdminSidebarLink
      to="/admin"
      className={
        collapsed
          ? "mx-auto flex h-9 w-9 items-center justify-center text-primary transition-opacity hover:opacity-80"
          : "flex items-center gap-2.5 px-1 text-primary transition-opacity hover:opacity-80"
      }
      aria-label="Tổng quan quản trị StayHub"
    >
      <img src={logoMark} alt="" className="h-9 w-9 shrink-0" />
      {!collapsed && (
        <span className="flex min-w-0 flex-col leading-tight">
          <span className="truncate font-display text-[19px] font-bold">StayHub</span>
          <span className="text-[10px] font-semibold uppercase tracking-[0.24em] text-accent">
            Quản trị
          </span>
        </span>
      )}
    </AdminSidebarLink>
  );
}
