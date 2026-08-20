import { useEffect, useMemo, useState } from "react";
import { createFileRoute, Link } from "@tanstack/react-router";
import {
  Bar,
  BarChart,
  CartesianGrid,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import { AlarmClock, CalendarClock, CheckCircle2, Download, Star } from "lucide-react";
import { toast } from "sonner";
import { AppShell } from "@/components/AppShell";
import { PriorityBadge, StatusBadge } from "@/components/badges";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Textarea } from "@/components/ui/textarea";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { useAuth } from "@/lib/auth";
import { completeFollowUp, getFollowUps, logAudit, scopeEvents, type FollowUpRow } from "@/lib/store";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/followups")({
  head: () => ({
    meta: [
      { title: "Follow-up Management — Adama 9141" },
      {
        name: "description",
        content:
          "Schedule, track and complete citizen follow-up calls for resolved Adama 9141 emergency reports.",
      },
      { property: "og:title", content: "Follow-up Management — Adama 9141" },
      {
        property: "og:description",
        content: "Track citizen follow-ups and satisfaction for Adama 9141 reports.",
      },
    ],
  }),
  component: FollowUpsPage,
});

const day = (d: string) => new Date(d).toDateString();

function FollowUpsPage() {
  const { user } = useAuth();
  const [rows, setRows] = useState<FollowUpRow[]>([]);
  const [active, setActive] = useState<FollowUpRow | null>(null);
  const [notes, setNotes] = useState("");
  const [satisfaction, setSatisfaction] = useState(5);

  useEffect(() => {
    const sync = () =>
      setRows(
        getFollowUps().filter((r) => scopeEvents([r.event], user).length > 0),
      );
    sync();
    window.addEventListener("a9141:change", sync);
    return () => window.removeEventListener("a9141:change", sync);
  }, [user]);

  const today = rows.filter((r) => r.followUp.status === "Pending" && day(r.followUp.dueAt) === new Date().toDateString());
  const overdue = rows.filter((r) => r.overdue);
  const completed = rows.filter((r) => r.followUp.status === "Completed");
  const completionRate = rows.length ? Math.round((completed.length / rows.length) * 100) : 0;
  const satisfactionScores = completed
    .map((r) => r.followUp.satisfaction)
    .filter((v): v is number => typeof v === "number");
  const avgSatisfaction = satisfactionScores.length
    ? (satisfactionScores.reduce((a, b) => a + b, 0) / satisfactionScores.length).toFixed(1)
    : "—";

  const byDept = useMemo(() => {
    const map = new Map<string, { dept: string; total: number; score: number; count: number }>();
    rows.forEach((r) => {
      const cur = map.get(r.event.department) ?? {
        dept: r.event.department.replace(" Department", ""),
        total: 0,
        score: 0,
        count: 0,
      };
      cur.total += 1;
      if (typeof r.followUp.satisfaction === "number") {
        cur.score += r.followUp.satisfaction;
        cur.count += 1;
      }
      map.set(r.event.department, cur);
    });
    return Array.from(map.values()).map((d) => ({
      dept: d.dept,
      followUps: d.total,
      satisfaction: d.count ? Number((d.score / d.count).toFixed(2)) : 0,
    }));
  }, [rows]);

  const trend = useMemo(() => {
    const buckets = new Map<string, number>();
    completed.forEach((r) => {
      const k = new Date(r.followUp.completedAt ?? r.followUp.dueAt).toLocaleDateString();
      buckets.set(k, (buckets.get(k) ?? 0) + 1);
    });
    return Array.from(buckets.entries())
      .slice(-14)
      .map(([date, count]) => ({ date, count }));
  }, [completed]);

  const exportCsv = () => {
    const head = ["Event", "Category", "Department", "Due", "Status", "Outcome", "Satisfaction", "Notes"];
    const body = rows.map((r) =>
      [
        r.event.id,
        r.event.category,
        r.event.department,
        new Date(r.followUp.dueAt).toLocaleString(),
        r.overdue ? "Overdue" : r.followUp.status,
        r.followUp.outcome ?? "",
        r.followUp.satisfaction ?? "",
        r.followUp.notes,
      ]
        .map((v) => `"${String(v).replace(/"/g, '""')}"`)
        .join(","),
    );
    const blob = new Blob(["\uFEFF" + [head.join(","), ...body].join("\n")], {
      type: "text/csv;charset=utf-8;",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `adama-9141-followups-${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
    logAudit(user?.fullName ?? "User", "Exported follow-up report");
    toast.success("Follow-up report downloaded");
  };

  const List = ({ items, empty }: { items: FollowUpRow[]; empty: string }) => (
    <div className="space-y-2">
      {items.length === 0 && <p className="p-4 text-sm text-muted-foreground">{empty}</p>}
      {items.map((r) => (
        <div
          key={r.followUp.id}
          className={cn(
            "flex flex-wrap items-center gap-2 rounded-lg border border-border p-3 transition hover:shadow-sm",
            r.overdue && "border-destructive/50 bg-destructive/5",
          )}
        >
          <Link
            to="/events/$id"
            params={{ id: r.event.id }}
            className="font-medium text-primary hover:underline"
          >
            {r.event.id}
          </Link>
          <PriorityBadge priority={r.event.priority} />
          <StatusBadge status={r.event.status} />
          <span className="text-xs text-muted-foreground">
            Due {new Date(r.followUp.dueAt).toLocaleString()}
          </span>
          {r.overdue && <Badge variant="destructive">Overdue</Badge>}
          {r.followUp.status === "Completed" && (
            <Badge className="bg-success text-white">
              {r.followUp.outcome} · {r.followUp.satisfaction}/5
            </Badge>
          )}
          <span className="w-full text-xs text-muted-foreground sm:w-auto sm:flex-1">
            {r.followUp.notes}
          </span>
          {r.followUp.status === "Pending" && (
            <Button
              size="sm"
              onClick={() => {
                setActive(r);
                setNotes("");
                setSatisfaction(5);
              }}
            >
              Complete
            </Button>
          )}
        </div>
      ))}
    </div>
  );

  return (
    <AppShell title="Follow-up Management">
      <div className="space-y-4">
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {[
            { label: "Today's follow-ups", value: today.length, icon: CalendarClock },
            { label: "Overdue", value: overdue.length, icon: AlarmClock },
            { label: "Completion rate", value: `${completionRate}%`, icon: CheckCircle2 },
            { label: "Avg. satisfaction", value: avgSatisfaction, icon: Star },
          ].map((s) => (
            <Card key={s.label}>
              <CardContent className="flex items-center gap-3 p-5">
                <s.icon className="size-7 text-primary" />
                <div>
                  <p className="font-display text-2xl font-bold">{s.value}</p>
                  <p className="text-xs text-muted-foreground">{s.label}</p>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>

        <div className="flex justify-end">
          <Button variant="outline" onClick={exportCsv}>
            <Download className="size-4" /> Export follow-up report
          </Button>
        </div>

        {overdue.length > 0 && (
          <Card className="border-destructive/50">
            <CardHeader className="pb-2">
              <CardTitle className="text-base text-destructive">Overdue follow-ups</CardTitle>
            </CardHeader>
            <CardContent>
              <List items={overdue} empty="None" />
            </CardContent>
          </Card>
        )}

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-base">Today</CardTitle>
          </CardHeader>
          <CardContent>
            <List items={today} empty="No follow-ups scheduled for today." />
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-base">All follow-ups</CardTitle>
          </CardHeader>
          <CardContent>
            <List items={rows} empty="No follow-ups have been scheduled yet." />
          </CardContent>
        </Card>

        <div className="grid gap-4 lg:grid-cols-2">
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-base">Satisfaction by department</CardTitle>
            </CardHeader>
            <CardContent className="h-64">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={byDept}>
                  <CartesianGrid strokeDasharray="3 3" opacity={0.25} />
                  <XAxis dataKey="dept" fontSize={11} />
                  <YAxis domain={[0, 5]} fontSize={11} />
                  <Tooltip />
                  <Bar dataKey="satisfaction" fill="#2563eb" radius={[6, 6, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-base">Completed follow-ups over time</CardTitle>
            </CardHeader>
            <CardContent className="h-64">
              <ResponsiveContainer width="100%" height="100%">
                <LineChart data={trend}>
                  <CartesianGrid strokeDasharray="3 3" opacity={0.25} />
                  <XAxis dataKey="date" fontSize={11} />
                  <YAxis allowDecimals={false} fontSize={11} />
                  <Tooltip />
                  <Line type="monotone" dataKey="count" stroke="#1e3a8a" strokeWidth={2} />
                </LineChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        </div>
      </div>

      <Dialog open={!!active} onOpenChange={(o) => !o && setActive(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Complete follow-up · {active?.event.id}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <Textarea
              rows={4}
              maxLength={300}
              placeholder="What did the citizen say?"
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
            />
            <div>
              <p className="mb-1 text-sm font-medium">Citizen satisfaction</p>
              <div className="flex gap-1">
                {[1, 2, 3, 4, 5].map((n) => (
                  <button key={n} type="button" onClick={() => setSatisfaction(n)}>
                    <Star
                      className={cn(
                        "size-6",
                        n <= satisfaction ? "fill-warning text-warning" : "text-muted-foreground",
                      )}
                    />
                  </button>
                ))}
              </div>
            </div>
          </div>
          <DialogFooter className="gap-2">
            <Button
              variant="destructive"
              onClick={() => {
                if (!active) return;
                completeFollowUp(
                  active.event.id,
                  active.followUp.id,
                  "Problem persists",
                  satisfaction,
                  notes,
                  user?.fullName ?? "User",
                );
                setActive(null);
                toast.warning("Event reopened as Unsolved");
              }}
            >
              Problem persists
            </Button>
            <Button
              onClick={() => {
                if (!active) return;
                completeFollowUp(
                  active.event.id,
                  active.followUp.id,
                  "Resolved",
                  satisfaction,
                  notes,
                  user?.fullName ?? "User",
                );
                setActive(null);
                toast.success("Follow-up completed");
              }}
            >
              Citizen confirms resolved
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </AppShell>
  );
}
