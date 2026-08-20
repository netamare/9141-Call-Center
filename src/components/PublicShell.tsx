import type { ReactNode } from "react";
import { Link } from "@tanstack/react-router";
import { Facebook, Mail, MapPin, Moon, Phone, Send, Sun, Twitter } from "lucide-react";
import { Brand } from "@/components/Brand";
import { LANGUAGES, useI18n } from "@/lib/i18n";
import { useTheme } from "@/lib/theme";
import { cn } from "@/lib/utils";

const LINKS = [
  { to: "/", label: "Home" },
  { to: "/report", label: "Report Emergency" },
  { to: "/portal", label: "Track Complaint" },
  { to: "/about", label: "About Us" },
  { to: "/contact", label: "Contact Us" },
];

export function PublicShell({ children }: { children: ReactNode }) {
  const { theme, toggleTheme } = useTheme();
  const { lang, setLang } = useI18n();

  return (
    <div className="app-watermark flex min-h-screen flex-col bg-background">
      <header className="sticky top-0 z-40 bg-brand-gradient text-primary-foreground shadow-brand">
        <div className="mx-auto flex max-w-6xl flex-wrap items-center gap-3 px-4 py-3">
          <Link to="/" className="shrink-0">
            <Brand size="sm" onDark tagline="Adama City Emergency Call Center" />
          </Link>
          <nav className="order-3 flex w-full flex-wrap gap-1 sm:order-none sm:ml-auto sm:w-auto">
            {LINKS.map((l) => (
              <Link
                key={l.to}
                to={l.to}
                activeOptions={{ exact: l.to === "/" }}
                className="rounded-full px-3 py-2 text-sm font-medium text-white/85 transition hover:bg-white/15"
                activeProps={{ className: "bg-white/25 text-white" }}
              >
                {l.label}
              </Link>
            ))}
          </nav>
          <div className="ml-auto flex items-center gap-1 sm:ml-0">
            <select
              aria-label="Language"
              value={lang}
              onChange={(e) => setLang(e.target.value as typeof lang)}
              className="rounded-full bg-white/15 px-2 py-1.5 text-xs font-medium text-white outline-none"
            >
              {LANGUAGES.map((l) => (
                <option key={l.code} value={l.code} className="text-foreground">
                  {l.label}
                </option>
              ))}
            </select>
            <button
              onClick={toggleTheme}
              aria-label="Theme"
              className="rounded-full p-2 hover:bg-white/15"
            >
              {theme === "dark" ? <Sun className="size-4" /> : <Moon className="size-4" />}
            </button>
            <Link
              to="/login"
              className={cn(
                "rounded-full bg-white/20 px-3 py-2 text-xs font-semibold transition hover:bg-white/30",
              )}
            >
              Staff Login
            </Link>
          </div>
        </div>
      </header>

      <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-8">{children}</main>

      <footer className="mt-auto bg-sidebar px-4 py-8 text-sidebar-foreground">
        <div className="mx-auto grid max-w-6xl gap-6 sm:grid-cols-3">
          <div>
            <Brand size="sm" onDark />
            <p className="mt-3 text-xs text-sidebar-foreground/70">
              Adama City Administration · Emergency response for every citizen, 24/7.
            </p>
          </div>
          <div className="space-y-1.5 text-sm">
            <p className="font-display font-semibold">Quick links</p>
            {LINKS.map((l) => (
              <Link
                key={l.to}
                to={l.to}
                className="block text-sidebar-foreground/75 hover:text-sidebar-foreground"
              >
                {l.label}
              </Link>
            ))}
          </div>
          <div className="space-y-1.5 text-sm text-sidebar-foreground/80">
            <p className="font-display font-semibold text-sidebar-foreground">Contact</p>
            <p className="flex items-center gap-2">
              <Phone className="size-4" /> 9141 · +251-221-112-233
            </p>
            <p className="flex items-center gap-2">
              <Mail className="size-4" /> info@adama9141.gov.et
            </p>
            <p className="flex items-center gap-2">
              <MapPin className="size-4" /> City Center, Adama, Oromia
            </p>
            <div className="flex gap-2 pt-2">
              <Facebook className="size-4" />
              <Twitter className="size-4" />
              <Send className="size-4" />
            </div>
          </div>
        </div>
        <p className="mx-auto mt-6 max-w-6xl text-xs text-sidebar-foreground/60">
          © {new Date().getFullYear()} Adama City 9141 Emergency Call Center.
        </p>
      </footer>
    </div>
  );
}
