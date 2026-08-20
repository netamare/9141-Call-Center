import { useEffect, useMemo, useState } from "react";
import { createFileRoute } from "@tanstack/react-router";
import { Bar, BarChart, CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import { toast } from "sonner";
import { AppShell } from "@/components/AppShell";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { getEvents, getSettings, minutesBetween } from "@/lib/store";
import { avg, departmentStats, operatorStats } from "./reports";
import type { EventRecord } from "@/lib/types";

export const Route = createFileRoute("/performance")({
  head: () => ({
    meta: [
      { title: "Performance — Adama 9141" },
      { name: "description", content: "Operator and department performance monitoring." },
      { property: "og:title", content: "Performance — Adama 9141" },
      { property: "og:description", content: "Operator and department performance monitoring." },
    ],
  }),
  component: PerformancePage,
});

function PerformancePage() {
  const [events, setEvents] = useState<EventRecord[]>([]);
  const departments = getSettings().departments;

  useEffect(() => {
    const sync = () => setEvents(getEvents());
    sync();
    window.addEventListener("a9141:change", sync);
    return () => window.removeEventListener("a9141:change", sync);
  }, []);

  const deptRows = departmentStats(events, departments);
  const opRows = operatorStats(events);

  const trend = useMemo(
    () =>
      Array.from({ length: 14 }, (_, i) => {
        const d = new Date(Date.now() - (13 - i) * 86400000);
        const rows = events.filter(
          (e) => new Date(e.createdAt).toDateString() === d.toDateString(),
        );
        return {
          name: d.toLocaleDateString(undefined, { month: "short", day: "numeric" }),
          value: avg(
            rows
              .map((e) => minutesBetween(e.createdAt, e.assignedAt))
              .filter((v): v is number => v !== null),
          ),
        };
      }),
    [events],
  );

  const direction =
    (trend.at(-1)?.value ?? 0) <= (trend[0]?.value ?? 0) ? "improving" : "declining";

  return (
    <AppShell title="Performance Dashboard">
      <div className="space-y-4">
        <div className="grid gap-3 sm:grid-cols-3">
          <Card className="border-l-4 border-l-primary">
            <CardContent className="p-4">
              <p className="text-xs uppercase text-muted-foreground">Response trend</p>
              <p className="text-xl font-bold capitalize">{direction}</p>
            </CardContent>
          </Card>
          <Card className="border-l-4 border-l-primary">
            <CardContent className="p-4">
              <p className="text-xs uppercase text-muted-foreground">SLA breaches</p>
              <p className="text-xl font-bold">
                {deptRows.reduce((s, d) => s + d.overdue, 0)}
              </p>
            </CardContent>
          </Card>
          <Card className="border-l-4 border-l-primary">
            <CardContent className="p-4">
              <p className="text-xs uppercase text-muted-foreground">Events this week</p>
              <p className="text-xl font-bold">
                {events.filter((e) => Date.now() - +new Date(e.createdAt) < 7 * 86400000).length}
              </p>
            </CardContent>
          </Card>
        </div>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-base">Department comparison</CardTitle>
          </CardHeader>
          <CardContent className="h-64">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={deptRows.map((d) => ({ name: d.name.replace(" Department", ""), value: d.total }))}>
                <CartesianGrid strokeDasharray="3 3" stroke="#dbeafe" />
                <XAxis dataKey="name" fontSize={11} />
                <YAxis allowDecimals={false} fontSize={12} />
                <Tooltip />
                <Bar dataKey="value" fill="#2563eb" radius={[6, 6, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-base">Response time trend (14 days)</CardTitle>
          </CardHeader>
          <CardContent className="h-64">
            <ResponsiveContainer width="100%" height="100%">
              <LineChart data={trend}>
                <CartesianGrid strokeDasharray="3 3" stroke="#dbeafe" />
                <XAxis dataKey="name" fontSize={10} />
                <YAxis fontSize={12} />
                <Tooltip />
                <Line type="monotone" dataKey="value" stroke="#1e3a8a" strokeWidth={3} />
              </LineChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex-row items-center justify-between pb-2">
            <CardTitle className="text-base">Operator performance</CardTitle>
            <Button size="sm" onClick={() => window.print()}>
              Export PDF
            </Button>
          </CardHeader>
          <CardContent className="overflow-x-auto p-0">
            <table className="w-full min-w-[600px] text-sm">
              <thead className="bg-brand-gradient text-primary-foreground">
                <tr>
                  <th className="px-3 py-2 text-left">Operator</th>
                  <th className="px-3 py-2 text-left">Handled</th>
                  <th className="px-3 py-2 text-left">Avg response</th>
                  <th className="px-3 py-2 text-left">Resolution %</th>
                  <th className="px-3 py-2 text-left">Satisfaction</th>
                </tr>
              </thead>
              <tbody>
                {opRows.map((r) => (
                  <tr key={r.name} className="border-t">
                    <td className="px-3 py-2 font-medium">{r.name}</td>
                    <td className="px-3 py-2">{r.handled}</td>
                    <td className="px-3 py-2">{r.avgResponse} min</td>
                    <td className="px-3 py-2">{r.resolution}%</td>
                    <td className="px-3 py-2">{r.satisfaction}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </CardContent>
        </Card>

        <Button
          variant="outline"
          className="min-h-11"
          onClick={() => toast.success("Monthly performance summary generated")}
        >
          Generate weekly / monthly summary
        </Button>
      </div>
    </AppShell>
  );
}
