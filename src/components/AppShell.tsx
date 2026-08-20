import { useEffect, useMemo, useState } from "react";
import type { ReactNode } from "react";
import { Link, useNavigate, useRouterState } from "@tanstack/react-router";
import {
  Activity,
  BarChart3,
  CalendarClock,
  Bell,
  FileText,
  Gauge,
  Globe,
  LayoutDashboard,
  List,
  LogOut,
  Map,
  Menu,
  Moon,
  PlusCircle,
  Radio,
  Settings as SettingsIcon,
  Sun,
  Users,
  WifiOff,
  X,
} from "lucide-react";
import { useAuth } from "@/lib/auth";
import {
  downloadWeeklyReport,
  getNotifications,
  getQueue,
  runEscalationCheck,
  setNotifications,
} from "@/lib/store";
import { LANGUAGES, useI18n } from "@/lib/i18n";
import { useTheme } from "@/lib/theme";
import type { Notification, Role } from "@/lib/types";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { cn } from "@/lib/utils";
import logo from "@/assets/adama-city-logo.jpg.asset.json";

interface NavItem {
  to: string;
  label: string;
  key: string;
  icon: typeof LayoutDashboard;
  roles: Role[];
}

export const NAV: NavItem[] = [
  {
    to: "/dashboard",
    label: "Dashboard",
    key: "nav.dashboard",
    icon: LayoutDashboard,
    roles: ["admin", "operator", "supervisor", "officer"],
  },
  {
    to: "/events/new",
    label: "New Event",
    key: "nav.newEvent",
    icon: PlusCircle,
    roles: ["operator", "admin"],
  },
  {
    to: "/events",
    label: "Event List",
    key: "nav.events",
    icon: List,
    roles: ["admin", "operator", "supervisor", "officer"],
  },
  { to: "/field", label: "Field View", key: "nav.field", icon: Radio, roles: ["officer", "admin"] },
  {
    to: "/followups",
    label: "Follow-ups",
    key: "nav.followups",
    icon: CalendarClock,
    roles: ["admin", "supervisor", "operator"],
  },
  {
    to: "/reports",
    label: "Reports & Analytics",
    key: "nav.reports",
    icon: BarChart3,
    roles: ["admin", "supervisor"],
  },
  {
    to: "/performance",
    label: "Performance",
    key: "nav.performance",
    icon: Gauge,
    roles: ["admin", "supervisor"],
  },
  {
    to: "/heatmap",
    label: "Heat Map",
    key: "nav.heatmap",
    icon: Map,
    roles: ["admin", "operator", "supervisor", "officer"],
  },
  {
    to: "/portal",
    label: "Citizen Portal",
    key: "nav.portal",
    icon: FileText,
    roles: ["admin", "operator", "supervisor", "officer"],
  },
  { to: "/users", label: "Users", key: "nav.users", icon: Users, roles: ["admin"] },
  { to: "/settings", label: "Settings", key: "nav.settings", icon: SettingsIcon, roles: ["admin"] },
  { to: "/health", label: "System Health", key: "nav.health", icon: Activity, roles: ["admin"] },
];

function useNotifications() {
  const [items, setItems] = useState<Notification[]>([]);
  useEffect(() => {
    const sync = () => setItems(getNotifications());
    sync();
    window.addEventListener("a9141:change", sync);
    const t = window.setInterval(sync, 10000);
    return () => {
      window.removeEventListener("a9141:change", sync);
      window.clearInterval(t);
    };
  }, []);
  return items;
}

export function AppShell({ title, children }: { title: string; children: ReactNode }) {
  const { user, ready, logout } = useAuth();
  const { t, lang, setLang } = useI18n();
  const { theme, toggleTheme } = useTheme();
  const navigate = useNavigate();
  const pathname = useRouterState({ select: (s) => s.location.pathname });
  const [open, setOpen] = useState(false);
  const [offline, setOffline] = useState(false);
  const [queued, setQueued] = useState(0);
  const notifications = useNotifications();
  const unread = notifications.filter((n) => !n.read).length;

  useEffect(() => {
    if (ready && !user) navigate({ to: "/", replace: true });
  }, [ready, user, navigate]);

  useEffect(() => {
    const sync = () => {
      setOffline(!navigator.onLine);
      setQueued(getQueue().length);
    };
    sync();
    window.addEventListener("online", sync);
    window.addEventListener("offline", sync);
    window.addEventListener("a9141:change", sync);
    return () => {
      window.removeEventListener("online", sync);
      window.removeEventListener("offline", sync);
      window.removeEventListener("a9141:change", sync);
    };
  }, []);

  useEffect(() => {
    if (!user) return;
    const t2 = window.setInterval(() => runEscalationCheck(user.fullName), 30000);
    return () => window.clearInterval(t2);
  }, [user]);

  const items = useMemo(
    () => (user ? NAV.filter((n) => n.roles.includes(user.role)) : []),
    [user],
  );

  if (!ready || !user) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-background">
        <div className="h-10 w-10 animate-spin rounded-full border-4 border-primary border-t-transparent" />
      </div>
    );
  }

  const sidebar = (
    <nav className="flex h-full flex-col gap-1 overflow-y-auto p-3">
      {items.map((item) => {
        const active = pathname === item.to || pathname.startsWith(item.to + "/");
        return (
          <Link
            key={item.to}
            to={item.to}
            onClick={() => setOpen(false)}
            className={cn(
              "flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-sidebar-foreground/85 transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground",
              active && "bg-sidebar-primary text-sidebar-primary-foreground shadow-brand",
            )}
          >
            <item.icon className="size-4 shrink-0" />
            {t(item.key)}
          </Link>
        );
      })}
      <button
        onClick={logout}
        className="mt-2 flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-sidebar-foreground/85 transition-colors hover:bg-sidebar-accent"
      >
        <LogOut className="size-4" /> {t("action.logout")}
      </button>
    </nav>
  );

  const brand = (
    <>
      <img
        src={logo.url}
        alt="Adama City Administration logo"
        className="size-10 shrink-0 rounded-full bg-white object-contain p-0.5"
      />
      <div className="leading-tight">
        <p className="text-sm font-semibold text-sidebar-foreground">{t("app.name")}</p>
        <p className="text-xs text-sidebar-foreground/70">{t("app.subtitle")}</p>
      </div>
    </>
  );

  return (
    <div className="app-watermark flex min-h-screen w-full bg-background">
      <aside className="sticky top-0 hidden h-screen w-64 shrink-0 flex-col bg-sidebar lg:flex">
        <div className="flex items-center gap-3 border-b border-sidebar-border px-4 py-4">{brand}</div>
        {sidebar}
      </aside>

      {open && (
        <div className="fixed inset-0 z-50 lg:hidden">
          <div className="absolute inset-0 bg-black/50" onClick={() => setOpen(false)} />
          <div className="absolute left-0 top-0 h-full w-72 bg-sidebar">
            <div className="flex items-center justify-between gap-2 border-b border-sidebar-border px-4 py-3">
              <div className="flex min-w-0 items-center gap-2">{brand}</div>
              <button onClick={() => setOpen(false)} className="text-sidebar-foreground">
                <X className="size-5" />
              </button>
            </div>
            {sidebar}
          </div>
        </div>
      )}

      <div className="flex min-w-0 flex-1 flex-col pb-16 lg:pb-0">
        <header className="sticky top-0 z-30 flex items-center gap-2 bg-brand-gradient px-3 py-3 text-primary-foreground shadow-brand sm:gap-3 sm:px-4">
          <button className="lg:hidden" onClick={() => setOpen(true)} aria-label="Open menu">
            <Menu className="size-6" />
          </button>
          <h1 className="min-w-0 flex-1 truncate text-base font-semibold sm:text-lg">{title}</h1>

          {offline && (
            <span className="hidden items-center gap-1 rounded-full bg-white/15 px-2 py-1 text-xs sm:flex">
              <WifiOff className="size-3.5" /> {t("label.offline")}
              {queued > 0 && ` · ${queued}`}
            </span>
          )}

          {user.role === "admin" && (
            <Button
              size="sm"
              variant="ghost"
              className="hidden text-primary-foreground hover:bg-white/15 md:inline-flex"
              onClick={() => downloadWeeklyReport(user.fullName)}
            >
              {t("action.downloadWeekly")}
            </Button>
          )}

          <button
            onClick={toggleTheme}
            className="rounded-full p-2 hover:bg-white/15"
            aria-label={t("label.theme")}
          >
            {theme === "dark" ? <Sun className="size-5" /> : <Moon className="size-5" />}
          </button>

          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <button
                className="flex items-center gap-1 rounded-full p-2 hover:bg-white/15"
                aria-label={t("label.language")}
              >
                <Globe className="size-5" />
                <span className="text-xs font-semibold">
                  {LANGUAGES.find((l) => l.code === lang)?.short}
                </span>
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuLabel>{t("label.language")}</DropdownMenuLabel>
              <DropdownMenuSeparator />
              {LANGUAGES.map((l) => (
                <DropdownMenuItem
                  key={l.code}
                  onClick={() => setLang(l.code)}
                  className={cn(lang === l.code && "font-semibold text-primary")}
                >
                  {l.label}
                </DropdownMenuItem>
              ))}
            </DropdownMenuContent>
          </DropdownMenu>

          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <button
                className="relative rounded-full p-2 hover:bg-white/15"
                aria-label={t("label.notifications")}
              >
                <Bell className="size-5" />
                {unread > 0 && (
                  <span className="absolute -right-0.5 -top-0.5 flex size-5 items-center justify-center rounded-full bg-destructive text-[10px] font-bold">
                    {unread}
                  </span>
                )}
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80">
              <DropdownMenuLabel className="flex items-center justify-between">
                {t("label.notifications")}
                <Button
                  size="sm"
                  variant="ghost"
                  onClick={() =>
                    setNotifications(getNotifications().map((n) => ({ ...n, read: true })))
                  }
                >
                  {t("action.markAllRead")}
                </Button>
              </DropdownMenuLabel>
              <DropdownMenuSeparator />
              <div className="max-h-80 overflow-y-auto">
                {notifications.length === 0 && (
                  <p className="p-4 text-sm text-muted-foreground">{t("label.noNotifications")}</p>
                )}
                {notifications.slice(0, 20).map((n) => (
                  <DropdownMenuItem
                    key={n.id}
                    className={cn("flex flex-col items-start gap-0.5", !n.read && "bg-accent/60")}
                    onClick={() => {
                      setNotifications(
                        getNotifications().map((x) => (x.id === n.id ? { ...x, read: true } : x)),
                      );
                      if (n.eventId) navigate({ to: "/events/$id", params: { id: n.eventId } });
                    }}
                  >
                    <span className="text-sm font-medium">{n.title}</span>
                    <span className="text-xs text-muted-foreground">{n.body}</span>
                    <span className="text-[10px] text-muted-foreground">
                      {new Date(n.at).toLocaleString()}
                    </span>
                  </DropdownMenuItem>
                ))}
              </div>
            </DropdownMenuContent>
          </DropdownMenu>

          <div className="hidden text-right sm:block">
            <p className="text-sm font-medium leading-tight">{user.fullName}</p>
            <p className="text-xs capitalize opacity-85">{user.role}</p>
          </div>
          <Badge className="bg-white/20 capitalize sm:hidden">{user.role}</Badge>
        </header>

        <main className="min-w-0 flex-1 p-4 sm:p-6">{children}</main>
      </div>

      <nav className="fixed bottom-0 left-0 right-0 z-40 flex justify-around border-t border-sidebar-border bg-sidebar px-1 py-1 lg:hidden">
        {items.slice(0, 5).map((item) => {
          const active = pathname === item.to;
          return (
            <Link
              key={item.to}
              to={item.to}
              className={cn(
                "flex min-h-11 min-w-11 flex-1 flex-col items-center justify-center gap-0.5 rounded-lg px-1 py-1.5 text-[10px] text-sidebar-foreground/80",
                active && "bg-sidebar-primary text-sidebar-primary-foreground",
              )}
            >
              <item.icon className="size-5" />
              <span className="truncate">{t(item.key).split(" ")[0]}</span>
            </Link>
          );
        })}
      </nav>
    </div>
  );
}
