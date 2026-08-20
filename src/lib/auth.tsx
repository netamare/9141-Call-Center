import { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react";
import type { ReactNode } from "react";
import { useNavigate } from "@tanstack/react-router";
import { ensureSeed, getSession, getUsers, logAudit, setSession } from "./store";
import type { User } from "./types";

interface AuthCtx {
  user: User | null;
  ready: boolean;
  login: (username: string, password: string) => { ok: boolean; error?: string };
  logout: () => void;
}

const Ctx = createContext<AuthCtx>({
  user: null,
  ready: false,
  login: () => ({ ok: false }),
  logout: () => {},
});

const TIMEOUT_MS = 30 * 60 * 1000;

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [ready, setReady] = useState(false);
  const navigate = useNavigate();

  useEffect(() => {
    ensureSeed();
    const s = getSession();
    if (s && Date.now() - s.at < TIMEOUT_MS) {
      const u = getUsers().find((x) => x.id === s.userId) ?? null;
      setUser(u);
      setSession({ userId: s.userId, at: Date.now() });
    } else if (s) {
      setSession(null);
    }
    setReady(true);
  }, []);

  const logout = useCallback(() => {
    if (user) logAudit(user.fullName, "Logged out");
    setSession(null);
    setUser(null);
    navigate({ to: "/", replace: true });
  }, [navigate, user]);

  useEffect(() => {
    if (!user) return;
    const touch = () => setSession({ userId: user.id, at: Date.now() });
    const events = ["click", "keydown", "mousemove", "touchstart"];
    events.forEach((e) => window.addEventListener(e, touch, { passive: true }));
    const t = window.setInterval(() => {
      const s = getSession();
      if (!s || Date.now() - s.at > TIMEOUT_MS) logout();
    }, 30000);
    return () => {
      events.forEach((e) => window.removeEventListener(e, touch));
      window.clearInterval(t);
    };
  }, [user, logout]);

  const login = useCallback((username: string, password: string) => {
    ensureSeed();
    const u = getUsers().find((x) => x.username === username.trim());
    if (!u || u.password !== password) return { ok: false, error: "Invalid username or password" };
    if (!u.active) return { ok: false, error: "This account is deactivated" };
    setSession({ userId: u.id, at: Date.now() });
    setUser(u);
    logAudit(u.fullName, "Logged in");
    return { ok: true };
  }, []);

  const value = useMemo(() => ({ user, ready, login, logout }), [user, ready, login, logout]);
  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}

export const useAuth = () => useContext(Ctx);
