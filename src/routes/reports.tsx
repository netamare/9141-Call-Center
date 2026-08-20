import { useEffect, useMemo, useState } from "react";
import { createFileRoute } from "@tanstack/react-router";
import { Download, FileDown } from "lucide-react";
import { toast } from "sonner";
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
import { PRIORITY_COLOR } from "@/components/badges";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { getEvents, getSettings, minutesBetween } from "@/lib/store";
import { CATEGORIES, PRIORITIES, STATUSES, type EventRecord } from "@/lib/types";

export const Route = createFileRoute("/reports")({
  head: () => ({
    meta: [
      { title: "Reports & Analytics — Adama 9141" },
      { name: "description", content: "Department and response analytics for Adama City 9141." },
      { property: "og:title", content: "Reports & Analytics — Adama 9141" },
      { property: "og:description", content: "Department and response analytics." },
    ],
  }),
  component: ReportsPage,
});

const BLUES = ["#1e3a8a", "#2563eb", "#60a5fa", "#bfdbfe", "#172554"];
const ALL = "all";

export function avg(values: number[]) {
  return values.length ? Math.round(values.reduce((a, b) => a + b, 0) / values.length) : 0;
}

export function departmentStats(events: EventRecord[], departments: string[]) {
  const sla = getSettings().sla;
  return departments.map((d) => {
    const rows = events.filter((e) => e.department === d);
    const responses = rows
      .map((e) => minutesBetween(e.createdAt, e.assignedAt))
      .filter((v): v is number => v !== null);
    const solved = rows.filter((e) => e.status === "Solved").length;
    const compliant = rows.filter((e) => {
      const r = minutesBetween(e.createdAt, e.assignedAt);
      return r !== null && r <= sla[e.priority];
    }).length;
    return {
      name: d,
      total: rows.length,
      avgResponse: avg(responses),
      resolution: rows.length ? Math.round((solved / rows.length) * 100) : 0,
      slaCompliance: rows.length ? Math.round((compliant / rows.length) * 100) : 0,
      overdue: rows.filter((e) => {
        const r = minutesBetween(e.createdAt, e.assignedAt);
        return r === null || r > sla[e.priority];
      }).length,
    };
  });
}

export function operatorStats(events: EventRecord[]) {
  const names = Array.from(new Set(events.map((e) => e.operator)));
  return names.map((n) => {
    const rows = events.filter((e) => e.operator === n);
    const responses = rows
      .map((e) => minutesBetween(e.createdAt, e.assignedAt))
      .filter((v): v is number => v !== null);
    const ratings = rows.map((e) => e.feedback?.rating).filter((v): v is number => !!v);
    return {
      name: n,
      handled: rows.length,
      avgResponse: avg(responses),
      resolution: rows.length
        ? Math.round((rows.filter((e) => e.status === "Solved").length / rows.length) * 100)
        : 0,
      satisfaction: ratings.length ? (avg(ratings.map((r) => r * 10)) / 10).toFixed(1) : "—",
    };
  });
}

function ReportsPage() {
  const [events, setEvents] = useState<EventRecord[]>([]);
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [dept, setDept] = useState(ALL);
  const [cat, setCat] = useState(ALL);
  const [range, setRange] = useState("7");
  const departments = getSettings().departments;

  useEffect(() => {
    const sync = () => setEvents(getEvents());
    sync();
    window.addEventListener("a9141:change", sync);
    return () => window.removeEventListener("a9141:change", sync);
  }, []);

  const filtered = useMemo(
    () =>
      events.filter((e) => {
        const t = +new Date(e.createdAt);
        return (
          (dept === ALL || e.department === dept) &&
          (cat === ALL || e.category === cat) &&
          (!from || t >= +new Date(from)) &&
          (!to || t <= +new Date(to) + 86400000)
        );
      }),
    [events, dept, cat, from, to],
  );

  const byCategory = CATEGORIES.map((c) => ({
    name: c.split(" ")[0],
    value: filtered.filter((e) => e.category === c).length,
  }));
  const byPriority = PRIORITIES.map((p) => ({
    name: p,
    value: filtered.filter((e) => e.priority === p).length,
  }));
  const byStatus = STATUSES.map((s) => ({
    name: s,
    value: filtered.filter((e) => e.status === s).length,
  }));
  const byDept = departments.map((d) => ({
    name: d.replace(" Department", ""),
    value: filtered.filter((e) => e.department === d).length,
  }));
  const byHour = Array.from({ length: 24 }, (_, h) => ({
    name: `${h}:00`,
    value: filtered.filter((e) => new Date(e.createdAt).getHours() === h).length,
  }));
  const trend = Array.from({ length: Number(range) }, (_, i) => {
    const d = new Date(Date.now() - (Number(range) - 1 - i) * 86400000);
    const rows = filtered.filter(
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
  });

  const deptRows = departmentStats(filtered, departments);
  const opRows = operatorStats(filtered);

  const exportCsv = () => {
    const csv = [
      "Department,Total,Avg Response (min),Resolution %,SLA %,Overdue",
      ...deptRows.map((r) =>
        [r.name, r.total, r.avgResponse, r.resolution, r.slaCompliance, r.overdue].join(","),
      ),
    ].join("\n");
    const url = URL.createObjectURL(new Blob([csv], { type: "text/csv" }));
    const a = document.createElement("a");
    a.href = url;
    a.download = "department-report.csv";
    a.click();
    URL.revokeObjectURL(url);
    toast.success("CSV exported");
  };

  return (
    <AppShell title="Reports & Analytics">
      <div className="space-y-4">
        <Card>
          <CardContent className="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5">
            <div className="space-y-1">
              <Label className="text-xs">From</Label>
              <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">To</Label>
              <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">Department</Label>
              <Select value={dept} onValueChange={setDept}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={ALL}>All</SelectItem>
                  {departments.map((d) => (
                    <SelectItem key={d} value={d}>
                      {d}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label className="text-xs">Category</Label>
              <Select value={cat} onValueChange={setCat}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={ALL}>All</SelectItem>
                  {CATEGORIES.map((c) => (
                    <SelectItem key={c} value={c}>
                      {c}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label className="text-xs">Trend range</Label>
              <Select value={range} onValueChange={setRange}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="7">Last 7 days</SelectItem>
                  <SelectItem value="30">Last 30 days</SelectItem>
                  <SelectItem value="90">Last 90 days</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </CardContent>
        </Card>

        <div className="flex flex-wrap gap-2">
          <Button className="min-h-11" onClick={exportCsv}>
            <Download className="size-4" /> Export CSV
          </Button>
          <Button className="min-h-11" variant="secondary" onClick={() => window.print()}>
            <FileDown className="size-4" /> Export PDF (print)
          </Button>
          <Button
            className="min-h-11"
            variant="outline"
            onClick={() => toast.success("Weekly report scheduled every Monday 08:00")}
          >
            Schedule report
          </Button>
        </div>

        <div className="grid gap-4 xl:grid-cols-2">
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-base">Events by category</CardTitle>
            </CardHeader>
            <CardContent className="h-64">
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie data={byCategory} dataKey="value" nameKey="name" outerRadius={80} label>
                    {byCategory.map((_, i) => (
                      <Cell key={i} fill={BLUES[i % BLUES.length]} />
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
              <CardTitle className="text-base">Events by priority</CardTitle>
            </CardHeader>
            <CardContent className="h-64">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={byPriority}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#dbeafe" />
                  <XAxis dataKey="name" fontSize={12} />
                  <YAxis allowDecimals={false} fontSize={12} />
                  <Tooltip />
                  <Bar dataKey="value" radius={[6, 6, 0, 0]}>
                    {byPriority.map((p) => (
                      <Cell key={p.name} fill={PRIORITY_COLOR[p.name as "P1"]} />
                    ))}
                  </Bar>
                </BarChart>
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
                  <Pie data={byStatus} dataKey="value" nameKey="name" innerRadius={45} outerRadius={80}>
                    {byStatus.map((_, i) => (
                      <Cell key={i} fill={BLUES[i % BLUES.length]} />
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
              <CardTitle className="text-base">Average response time trend (min)</CardTitle>
            </CardHeader>
            <CardContent className="h-64">
              <ResponsiveContainer width="100%" height="100%">
                <LineChart data={trend}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#dbeafe" />
                  <XAxis dataKey="name" fontSize={11} />
                  <YAxis fontSize={12} />
                  <Tooltip />
                  <Line type="monotone" dataKey="value" stroke="#1e3a8a" strokeWidth={3} />
                </LineChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-base">Events by department</CardTitle>
            </CardHeader>
            <CardContent className="h-64">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={byDept} layout="vertical">
                  <CartesianGrid strokeDasharray="3 3" stroke="#dbeafe" />
                  <XAxis type="number" allowDecimals={false} fontSize={12} />
                  <YAxis type="category" dataKey="name" width={120} fontSize={11} />
                  <Tooltip />
                  <Bar dataKey="value" fill="#2563eb" radius={[0, 6, 6, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-base">Events by hour (peak times)</CardTitle>
            </CardHeader>
            <CardContent className="h-64">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={byHour}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#dbeafe" />
                  <XAxis dataKey="name" fontSize={9} interval={2} />
                  <YAxis allowDecimals={false} fontSize={12} />
                  <Tooltip />
                  <Bar dataKey="value" fill="#1e3a8a" radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        </div>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-base">Department performance</CardTitle>
          </CardHeader>
          <CardContent className="overflow-x-auto p-0">
            <table className="w-full min-w-[720px] text-sm">
              <thead className="bg-brand-gradient text-primary-foreground">
                <tr>
                  <th className="px-3 py-2 text-left">Department</th>
                  <th className="px-3 py-2 text-left">Total</th>
                  <th className="px-3 py-2 text-left">Avg response</th>
                  <th className="px-3 py-2 text-left">Resolution %</th>
                  <th className="px-3 py-2 text-left">SLA %</th>
                  <th className="px-3 py-2 text-left">Overdue</th>
                </tr>
              </thead>
              <tbody>
                {deptRows.map((r) => (
                  <tr key={r.name} className="border-t">
                    <td className="px-3 py-2 font-medium">{r.name}</td>
                    <td className="px-3 py-2">{r.total}</td>
                    <td className="px-3 py-2">{r.avgResponse} min</td>
                    <td className="px-3 py-2">{r.resolution}%</td>
                    <td className="px-3 py-2">{r.slaCompliance}%</td>
                    <td className="px-3 py-2">{r.overdue}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-base">Operator performance</CardTitle>
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
      </div>
    </AppShell>
  );
}
