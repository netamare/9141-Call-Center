import { useEffect, useMemo, useRef, useState } from "react";
import { createFileRoute, Link } from "@tanstack/react-router";
import {
  AlertTriangle,
  CheckCircle2,
  Clock,
  FileWarning,
  Layers,
  Star,
  TrendingUp,
} from "lucide-react";
import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Legend,
  Line,
  LineChart,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import { AppShell } from "@/components/AppShell";
import { PriorityBadge, StatusBadge, PRIORITY_COLOR } from "@/components/badges";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { getAudit, getEvents } from "@/lib/store";
import { CATEGORIES, PRIORITIES, STATUSES } from "@/lib/types";
import type { EventRecord } from "@/lib/types";

export const Route = createFileRoute("/dashboard")({
  head: () => ({
    meta: [
      { title: "Dashboard — Adama 9141" },
      { name: "description", content: "Live emergency statistics for Adama City 9141." },
      { property: "og:title", content: "Dashboard — Adama 9141" },
      { property: "og:description", content: "Live emergency statistics for Adama City 9141." },
    ],
  }),
  component: DashboardPage,
});

const BLUES = ["#1e3a8a", "#2563eb", "#60a5fa", "#bfdbfe", "#172554"];

function useCounter(value: number) {
  const [display, setDisplay] = useState(0);
  const prev = useRef(0);
  useEffect(() => {
    const from = prev.current;
    const start = performance.now();
    let raf = 0;
    const tick = (now: number) => {
      const p = Math.min(1, (now - start) / 600);
      setDisplay(Math.round(from + (value - from) * p));
      if (p < 1) raf = requestAnimationFrame(tick);
      else prev.current = value;
    };
    raf = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(raf);
  }, [value]);
  return display;
}

function StatCard({
  label,
  value,
  icon: Icon,
}: {
  label: string;
  value: number;
  icon: typeof Layers;
}) {
  const shown = useCounter(value);
  return (
    <Card className="border-l-4 border-l-primary">
      <CardContent className="flex items-center justify-between p-4">
        <div>
          <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
            {label}
          </p>
          <p className="mt-1 text-2xl font-bold text-foreground">{shown}</p>
        </div>
        <div className="flex size-10 items-center justify-center rounded-xl bg-secondary text-primary">
          <Icon className="size-5" />
        </div>
      </CardContent>
    </Card>
  );
}

function DashboardPage() {
  const [events, setEvents] = useState<EventRecord[]>([]);
  const [audit, setAudit] = useState(getAudit());
  const [newCount, setNewCount] = useState(0);
  const seen = useRef<number | null>(null);

  useEffect(() => {
    const sync = () => {
      const list = getEvents();
      if (seen.current !== null && list.length > seen.current) {
        setNewCount((c) => c + (list.length - (seen.current ?? 0)));
      }
      seen.current = list.length;
      setEvents(list);
      setAudit(getAudit());
    };
    sync();
    window.addEventListener("a9141:change", sync);
    const t = window.setInterval(sync, 30000);
    return () => {
      window.removeEventListener("a9141:change", sync);
      window.clearInterval(t);
    };
  }, []);

  const stats = useMemo(() => {
    const today = new Date().toDateString();
    const feedbacks = events.filter((e) => e.feedback);
    return {
      total: events.length,
      today: events.filter((e) => new Date(e.createdAt).toDateString() === today).length,
      pending: events.filter((e) => ["New", "Assigned", "Ongoing"].includes(e.status)).length,
      solved: events.filter((e) => e.status === "Solved").length,
      unsolved: events.filter((e) => e.status === "Unsolved").length,
      escalated: events.filter((e) => e.escalations.length > 0).length,
      rating: feedbacks.length
        ? (
            feedbacks.reduce((s, e) => s + (e.feedback?.rating ?? 0), 0) / feedbacks.length
          ).toFixed(1)
        : "—",
    };
  }, [events]);

  const byCategory = CATEGORIES.map((c) => ({
    name: c.split(" ")[0],
    value: events.filter((e) => e.category === c).length,
  }));

  const byDay = Array.from({ length: 7 }, (_, i) => {
    const d = new Date(Date.now() - (6 - i) * 86400000);
    return {
      name: d.toLocaleDateString(undefined, { weekday: "short" }),
      value: events.filter((e) => new Date(e.createdAt).toDateString() === d.toDateString()).length,
    };
  });

  const byPriority = PRIORITIES.map((p) => ({
    name: p,
    value: events.filter((e) => e.priority === p).length,
  }));

  const byStatus = STATUSES.map((s) => ({
    name: s,
    value: events.filter((e) => e.status === s).length,
  }));

  const recent = [...events]
    .sort((a, b) => +new Date(b.createdAt) - +new Date(a.createdAt))
    .slice(0, 5);

  return (
    <AppShell title="Dashboard">
      <div className="space-y-6">
        {newCount > 0 && (
          <div className="flex items-center gap-2 rounded-xl border border-primary/30 bg-secondary px-4 py-2 text-sm text-secondary-foreground">
            <Badge className="bg-destructive">{newCount}</Badge> new event(s) arrived since you
            opened this page
          </div>
        )}

        <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
          <StatCard label="Total Events" value={stats.total} icon={Layers} />
          <StatCard label="Today" value={stats.today} icon={Clock} />
          <StatCard label="Pending" value={stats.pending} icon={TrendingUp} />
          <StatCard label="Solved" value={stats.solved} icon={CheckCircle2} />
          <StatCard label="Unsolved" value={stats.unsolved} icon={FileWarning} />
        </div>

        <div className="grid gap-3 sm:grid-cols-2">
          <Card className="border-l-4 border-l-destructive">
            <CardContent className="flex items-center gap-3 p-4">
              <AlertTriangle className="size-5 text-destructive" />
              <div>
                <p className="text-sm font-semibold">{stats.escalated} escalated events</p>
                <p className="text-xs text-muted-foreground">
                  Auto-escalation checks run every 30 seconds
                </p>
              </div>
            </CardContent>
          </Card>
          <Card className="border-l-4 border-l-primary">
            <CardContent className="flex items-center gap-3 p-4">
              <Star className="size-5 text-primary" />
              <div>
                <p className="text-sm font-semibold">Average citizen rating: {stats.rating}</p>
                <p className="text-xs text-muted-foreground">Based on submitted feedback</p>
              </div>
            </CardContent>
          </Card>
        </div>

        <div className="grid gap-4 xl:grid-cols-2">
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-base">Events by category</CardTitle>
            </CardHeader>
            <CardContent className="h-64">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={byCategory}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#dbeafe" />
                  <XAxis dataKey="name" fontSize={12} />
                  <YAxis allowDecimals={false} fontSize={12} />
                  <Tooltip />
                  <Bar dataKey="value" fill="#2563eb" radius={[6, 6, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-base">Events last 7 days</CardTitle>
            </CardHeader>
            <CardContent className="h-64">
              <ResponsiveContainer width="100%" height="100%">
                <LineChart data={byDay}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#dbeafe" />
                  <XAxis dataKey="name" fontSize={12} />
                  <YAxis allowDecimals={false} fontSize={12} />
                  <Tooltip />
                  <Line type="monotone" dataKey="value" stroke="#1e3a8a" strokeWidth={3} />
                </LineChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-base">Events by priority</CardTitle>
            </CardHeader>
            <CardContent className="h-64">
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie data={byPriority} dataKey="value" nameKey="name" outerRadius={80} label>
                    {byPriority.map((p) => (
                      <Cell
                        key={p.name}
                        fill={PRIORITY_COLOR[p.name as keyof typeof PRIORITY_COLOR]}
                      />
                    ))}
                  </Pie>
                  <Legend />
                  <Tooltip />
                </PieChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-base">Events by status</CardTitle>
            </CardHeader>
            <CardContent className="h-64">
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie
                    data={byStatus}
                    dataKey="value"
                    nameKey="name"
                    innerRadius={45}
                    outerRadius={80}
                  >
                    {byStatus.map((s, i) => (
                      <Cell key={s.name} fill={BLUES[i % BLUES.length]} />
                    ))}
                  </Pie>
                  <Legend />
                  <Tooltip />
                </PieChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        </div>

        <div className="grid gap-4 xl:grid-cols-3">
          <Card className="xl:col-span-2">
            <CardHeader className="pb-2">
              <CardTitle className="text-base">Recent events</CardTitle>
            </CardHeader>
            <CardContent className="overflow-x-auto p-0">
              <table className="w-full text-sm">
                <thead className="bg-secondary text-secondary-foreground">
                  <tr>
                    <th className="px-3 py-2 text-left">ID</th>
                    <th className="px-3 py-2 text-left">Caller</th>
                    <th className="px-3 py-2 text-left">Category</th>
                    <th className="px-3 py-2 text-left">Priority</th>
                    <th className="px-3 py-2 text-left">Status</th>
                    <th className="px-3 py-2 text-left">Date</th>
                  </tr>
                </thead>
                <tbody>
                  {recent.map((e) => (
                    <tr key={e.id} className="border-t hover:bg-accent/50">
                      <td className="px-3 py-2 font-medium">
                        <Link
                          to="/events/$id"
                          params={{ id: e.id }}
                          className="text-primary hover:underline"
                        >
                          {e.id}
                        </Link>
                      </td>
                      <td className="px-3 py-2">{e.callerName}</td>
                      <td className="px-3 py-2">{e.category}</td>
                      <td className="px-3 py-2">
                        <PriorityBadge priority={e.priority} />
                      </td>
                      <td className="px-3 py-2">
                        <StatusBadge status={e.status} />
                      </td>
                      <td className="whitespace-nowrap px-3 py-2 text-muted-foreground">
                        {new Date(e.createdAt).toLocaleString()}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-base">Live activity feed</CardTitle>
            </CardHeader>
            <CardContent className="max-h-72 space-y-3 overflow-y-auto">
              {audit.length === 0 && (
                <p className="text-sm text-muted-foreground">No recorded activity yet.</p>
              )}
              {audit.slice(0, 12).map((a) => (
                <div key={a.id} className="flex gap-2 text-sm">
                  <span className="mt-1.5 size-2 shrink-0 rounded-full bg-primary" />
                  <div>
                    <p className="font-medium">{a.action}</p>
                    <p className="text-xs text-muted-foreground">
                      {a.user} · {new Date(a.at).toLocaleString()}
                    </p>
                  </div>
                </div>
              ))}
            </CardContent>
          </Card>
        </div>
      </div>
    </AppShell>
  );
}
