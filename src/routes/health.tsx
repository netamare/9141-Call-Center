import { createFileRoute } from "@tanstack/react-router";
import { AppShell } from "@/components/AppShell";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { getAudit, getEvents, getQueue } from "@/lib/store";

export const Route = createFileRoute("/health")({
  head: () => ({
    meta: [
      { title: "System Health — Adama 9141" },
      { name: "description", content: "Monitoring of local data store, sync queue and activity." },
      { property: "og:title", content: "System Health — Adama 9141" },
      { property: "og:description", content: "Monitoring for the 9141 call center system." },
    ],
  }),
  component: HealthPage,
});

function HealthPage() {
  const events = getEvents();
  const queue = getQueue();
  const audit = getAudit();
  const online = typeof navigator !== "undefined" ? navigator.onLine : true;

  const stats = [
    { label: "Data store", value: online ? "Operational" : "Offline mode" },
    { label: "Stored events", value: String(events.length) },
    { label: "Pending sync queue", value: String(queue.length) },
    { label: "Audit entries", value: String(audit.length) },
    { label: "SMS gateway (simulated)", value: "Operational" },
    { label: "Map tiles", value: "Operational" },
  ];

  return (
    <AppShell title="System Health">
      <div className="space-y-4">
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {stats.map((s) => (
            <Card key={s.label} className="border-l-4 border-l-primary">
              <CardContent className="p-4">
                <p className="text-xs uppercase text-muted-foreground">{s.label}</p>
                <p className="text-lg font-bold">{s.value}</p>
              </CardContent>
            </Card>
          ))}
        </div>
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-base">Recent system activity</CardTitle>
          </CardHeader>
          <CardContent className="space-y-1 text-sm text-muted-foreground">
            {audit.slice(0, 20).map((a) => (
              <p key={a.id}>
                <span className="text-foreground">{a.user}</span> — {a.action} ·{" "}
                {new Date(a.at).toLocaleString()}
              </p>
            ))}
          </CardContent>
        </Card>
      </div>
    </AppShell>
  );
}
