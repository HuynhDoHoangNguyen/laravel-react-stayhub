import { Link, useNavigate, useLocation } from "react-router-dom";
import { Sparkles } from "lucide-react";
import { toast } from "sonner";
import logoMark from "@/assets/logo-mark.png";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import {
  BackIcon,
  BellIcon,
  BookingsIcon,
  ConsoleIcon,
  HotelIcon,
  RefundIcon,
  ReportsIcon,
  SearchIcon,
  SettingsIcon,
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
import { useAuth } from "@/hooks/useAuth";
import { useRefundPending } from "@/hooks/queries/manager";

const NAV = [
  { to: "/manage/hotels", label: "My Hotels", icon: HotelIcon },
  { to: "/manage/bookings", label: "Bookings", icon: BookingsIcon },
  { to: "/manage/refunds", label: "Refund Queue", icon: RefundIcon },
  { to: "/manage/reports", label: "Reports", icon: ReportsIcon },
  { to: "/manage/settings", label: "Settings", icon: SettingsIcon },
];

export function ManagerShell({ children }) {
  const { session, signOut } = useAuth();
  const navigate = useNavigate();
  const { pathname } = useLocation();
  const { data: refunds } = useRefundPending();
  const pendingCount = refunds?.length ?? 0;

  const handleSignOut = () => {
    signOut();
    toast.success("Signed out.");
    navigate("/");
  };

  return (
    <SidebarProvider>
      <div className="flex min-h-screen w-full bg-background">
        <Sidebar collapsible="icon" className="border-r border-border/60">
          <SidebarHeader className="border-b border-border/60 px-2 py-3 group-data-[collapsible=icon]:px-0">
            <BrandLink />
          </SidebarHeader>

          <SidebarContent>
            <SidebarGroup>
              <SidebarGroupLabel>Console</SidebarGroupLabel>
              <SidebarGroupContent>
                <SidebarMenu>
                  {NAV.map((item) => {
                    const active = pathname === item.to || pathname.startsWith(item.to + "/");
                    return (
                      <SidebarMenuItem key={item.to}>
                        <SidebarMenuButton asChild isActive={active}>
                          <Link to={item.to} className="flex items-center gap-2">
                            <item.icon className="h-4 w-4" />
                            <span>{item.label}</span>
                            {item.to === "/manage/refunds" && pendingCount > 0 && (
                              <span className="ml-auto rounded-full bg-destructive px-2 py-0.5 text-[10px] font-semibold text-destructive-foreground">
                                {pendingCount}
                              </span>
                            )}
                          </Link>
                        </SidebarMenuButton>
                      </SidebarMenuItem>
                    );
                  })}
                </SidebarMenu>
              </SidebarGroupContent>
            </SidebarGroup>

            <SidebarGroup>
              <SidebarGroupLabel>Account</SidebarGroupLabel>
              <SidebarGroupContent>
                <SidebarMenu>
                  <SidebarMenuItem>
                    <SidebarMenuButton asChild>
                      <Link to="/" className="flex items-center gap-2">
                        <BackIcon className="h-4 w-4" />
                        <span>Back to app</span>
                      </Link>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                  <SidebarMenuItem>
                    <SidebarMenuButton onClick={handleSignOut}>
                      <SignOutIcon className="h-4 w-4" />
                      <span>Sign out</span>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                </SidebarMenu>
              </SidebarGroupContent>
            </SidebarGroup>
          </SidebarContent>
        </Sidebar>

        <div className="flex min-w-0 flex-1 flex-col">
          <header className="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-border/60 bg-gradient-to-b from-background/95 to-background/75 px-4 backdrop-blur-xl">
            <SidebarTrigger className="text-ink" />

            <div className="hidden items-center gap-2 rounded-full border border-border/60 bg-background/70 px-3 py-1.5 text-[12px] font-medium uppercase tracking-[0.24em] text-muted-foreground shadow-[0_1px_0_rgba(255,255,255,0.6)_inset] md:inline-flex">
              <ConsoleIcon className="h-3.5 w-3.5 text-primary/70" />
              Manager console
            </div>

            <div className="ml-auto flex items-center gap-2">
              <button
                type="button"
                aria-label="Search"
                className="hidden h-9 w-9 items-center justify-center rounded-full border border-border/60 bg-background/70 text-muted-foreground transition-all hover:border-primary/40 hover:text-primary hover:shadow-[0_4px_18px_-8px_rgba(26,46,32,0.35)] sm:inline-flex"
              >
                <SearchIcon className="h-4 w-4" />
              </button>
              <button
                type="button"
                aria-label="Notifications"
                className="relative inline-flex h-9 w-9 items-center justify-center rounded-full border border-border/60 bg-background/70 text-muted-foreground transition-all hover:border-primary/40 hover:text-primary hover:shadow-[0_4px_18px_-8px_rgba(26,46,32,0.35)]"
              >
                <BellIcon className="h-4 w-4" />
                <span className="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-gradient-to-br from-amber-400 to-rose-500 ring-2 ring-background" />
              </button>

              <UserChip name={session?.name} email={session?.email} />
            </div>
          </header>

          <main className="flex-1 p-4 sm:p-6 lg:p-8">{children}</main>
        </div>
      </div>
    </SidebarProvider>
  );
}

function initialsOf(name, email) {
  const src = (name?.trim() || email?.trim() || "").split(/[\s@._-]+/).filter(Boolean);
  const s = ((src[0]?.[0] ?? "") + (src[1]?.[0] ?? "")).toUpperCase();
  return s || "SN";
}

function UserChip({ name, email }) {
  const label = name || email?.split("@")[0] || "Signed in";
  return (
    <div className="group inline-flex items-center gap-2.5 rounded-full border border-border/60 bg-background/80 py-1 pl-1 pr-3.5 shadow-[0_2px_10px_-6px_rgba(26,46,32,0.25)] transition-all hover:border-primary/40 hover:shadow-[0_6px_22px_-10px_rgba(26,46,32,0.4)]">
      <span className="relative">
        <Avatar className="h-8 w-8 border border-primary/15">
          <AvatarFallback className="bg-gradient-to-br from-primary to-primary/70 text-[12px] font-semibold tracking-wide text-primary-foreground">
            {initialsOf(name, email)}
          </AvatarFallback>
        </Avatar>
        <Sparkles className="absolute -right-0.5 -top-0.5 h-3 w-3 text-amber-400 drop-shadow-[0_0_4px_rgba(251,191,36,0.6)]" />
      </span>
      <div className="hidden flex-col leading-tight sm:flex">
        <span className="max-w-[130px] truncate text-[13px] font-medium text-ink/90">{label}</span>
        <span className="text-[10px] uppercase tracking-[0.18em] text-muted-foreground">Host</span>
      </div>
    </div>
  );
}

function BrandLink() {
  const { state } = useSidebar();
  const collapsed = state === "collapsed";
  return (
    <Link
      to="/"
      className={
        collapsed
          ? "mx-auto flex h-9 w-9 items-center justify-center text-primary transition-opacity hover:opacity-80"
          : "flex items-center gap-2.5 px-1 text-primary transition-opacity hover:opacity-80"
      }
      aria-label="StayNest home"
    >
      <img src={logoMark} alt="" className="h-9 w-9" />
      {!collapsed && (
        <span className="flex min-w-0 flex-col leading-tight">
          <span className="truncate font-display text-[17px]">StayNest</span>
          <span className="text-[10px] uppercase tracking-[0.24em] text-muted-foreground">
            Manager
          </span>
        </span>
      )}
    </Link>
  );
}
