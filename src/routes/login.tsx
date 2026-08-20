import { useEffect, useState } from "react";
import { Link, createFileRoute, useNavigate } from "@tanstack/react-router";
import { ShieldCheck, Lock, Moon, Sun, User as UserIcon } from "lucide-react";
import { toast } from "sonner";
import { useAuth } from "@/lib/auth";
import { LANGUAGES, useI18n } from "@/lib/i18n";
import { useTheme } from "@/lib/theme";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";
import { Brand } from "@/components/Brand";

export const Route = createFileRoute("/login")({
  head: () => ({
    meta: [
      { title: "Staff Sign In — Adama City 9141" },
      {
        name: "description",
        content:
          "Secure sign-in for operators, supervisors, officers and admins of the Adama City 9141 emergency call center.",
      },
      { property: "og:title", content: "Adama City 9141 Emergency Call Center" },
      {
        property: "og:description",
        content: "Emergency dispatch, escalation and analytics platform for Adama City.",
      },
    ],
  }),
  component: LoginPage,
});

const DEMO = [
  { role: "Admin · Alemeshet Ketema", username: "admin", password: "admin123" },
  { role: "Operator · Netsanet Amare", username: "operator", password: "op123" },
  { role: "Supervisor · Mohammedareb Ahmed", username: "supervisor", password: "sup123" },
  { role: "Office Head · Naol Abdulkadir", username: "officehead", password: "off123" },
  { role: "Traffic Police Dept.", username: "traffic", password: "traffic123" },
  { role: "Fire Brigade Dept.", username: "fire", password: "fire123" },
  { role: "Police Dept.", username: "police", password: "police123" },
  { role: "Administrative Office", username: "adminoffice", password: "adminoffice123" },
];

function LoginPage() {
  const { login, user, ready } = useAuth();
  const { t, lang, setLang } = useI18n();
  const { theme, toggleTheme } = useTheme();
  const navigate = useNavigate();
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (ready && user) navigate({ to: "/dashboard", replace: true });
  }, [ready, user, navigate]);

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!username.trim() || !password) {
      toast.error("Username and password are required");
      return;
    }
    setLoading(true);
    window.setTimeout(() => {
      const res = login(username.trim(), password);
      setLoading(false);
      if (!res.ok) {
        toast.error(res.error ?? "Login failed");
        return;
      }
      toast.success("Welcome back");
      navigate({ to: "/dashboard" });
    }, 350);
  };

  return (
    <div className="app-watermark flex min-h-screen flex-col items-center justify-center bg-brand-gradient px-4 py-10">
      <div className="mb-4 flex flex-wrap items-center justify-center gap-2">
        {LANGUAGES.map((l) => (
          <button
            key={l.code}
            type="button"
            onClick={() => setLang(l.code)}
            className={cn(
              "rounded-full px-3 py-1.5 text-xs font-medium text-white/85 ring-1 ring-white/30 transition hover:bg-white/15",
              lang === l.code && "bg-white/25 text-white",
            )}
          >
            {l.label}
          </button>
        ))}
        <button
          type="button"
          onClick={toggleTheme}
          aria-label={t("label.theme")}
          className="rounded-full p-2 text-white/85 ring-1 ring-white/30 transition hover:bg-white/15"
        >
          {theme === "dark" ? <Sun className="size-4" /> : <Moon className="size-4" />}
        </button>
      </div>

      <div className="w-full max-w-md rounded-2xl bg-card p-6 shadow-brand sm:p-8">
        <div className="mb-6">
          <Brand size="md" />
        </div>

        <form onSubmit={submit} className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="username">{t("label.username")}</Label>
            <div className="relative">
              <UserIcon className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-primary" />
              <Input
                id="username"
                className="pl-9"
                value={username}
                maxLength={40}
                onChange={(e) => setUsername(e.target.value.replace(/[^A-Za-z0-9._@-]/g, ""))}
                placeholder="admin"
                autoComplete="username"
              />
            </div>
          </div>
          <div className="space-y-2">
            <Label htmlFor="password">{t("label.password")}</Label>
            <div className="relative">
              <Lock className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-primary" />
              <Input
                id="password"
                type="password"
                className="pl-9"
                value={password}
                maxLength={64}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="••••••"
                autoComplete="current-password"
              />
            </div>
          </div>
          <Button type="submit" className="min-h-11 w-full" disabled={loading}>
            {loading ? t("action.signingIn") : t("action.signIn")}
          </Button>
        </form>

        <div className="mt-6 rounded-xl bg-secondary p-3">
          <p className="mb-2 flex items-center gap-1.5 text-xs font-semibold text-secondary-foreground">
            <ShieldCheck className="size-3.5" /> {t("label.demoAccounts")}
          </p>
          <div className="grid gap-2 sm:grid-cols-2">
            {DEMO.map((d) => (
              <button
                key={d.username}
                type="button"
                onClick={() => {
                  setUsername(d.username);
                  setPassword(d.password);
                }}
                className="min-h-11 rounded-lg bg-card px-2 py-1.5 text-left text-xs shadow-sm transition hover:ring-2 hover:ring-ring"
              >
                <span className="block font-semibold text-foreground">{d.role}</span>
                <span className="text-muted-foreground">
                  {d.username} / {d.password}
                </span>
              </button>
            ))}
          </div>
        </div>
      </div>
      <Link to="/" className="mt-6 text-center text-xs text-white/85 underline-offset-4 hover:underline">
        ← Back to the public 9141 website
      </Link>
    </div>
  );
}

